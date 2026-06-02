<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\Product\Product;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\AccountPayable;
use App\Models\Partner\AporteCapital;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PurchaseManualController extends Controller
{
    public function store(Request $request)
    {
        //Corregir la compra manual y al ingresar productos.

        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'invoice_number' => 'required|string',
            'issue_date' => 'required|date',
            'payment_type' => 'required|string|in:efectivo,credito,aporte',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.item_type' => 'required|integer',
            'items.*.product_categorie_id' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            // 1. Create the Invoice (Purchase)
            $accessKey = 'MANUAL-' . strtoupper(Str::random(10)) . '-' . time();
            $issueDate = Carbon::parse($request->issue_date);

            $invoice = Invoice::create([
                'supplier_id' => $request->supplier_id,
                'access_key' => $accessKey,
                'invoice_number' => $request->invoice_number,
                'issue_date' => $issueDate,
                'subtotal' => $request->subtotal,
                'discount' => 0,
                'tax' => $request->tax,
                'total' => $request->total,
                'invoice_process' => 1, // Ya procesado, sumaremos el stock ahora mismo
                'created_by' => auth()->id() ?? 1,
            ]);

            // 2. Create Invoice Items & Update Stock
            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'code' => $item['code'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $item['tax'],
                    'total' => $item['total'],
                    'item_type' => $item['item_type'],
                    'product_categorie_id' => $item['product_categorie_id'],
                ]);

                // Update or Create Product Stock
                if ($item['item_type'] == 1) { // 1 = Producto Físico
                    $product = Product::where('sku', $item['code'])
                        ->orWhere('description', $item['description'])
                        ->first();

                    if (!$product) {
                        Product::create([
                            'description' => $item['description'],
                            'sku' => $item['code'],
                            'product_categorie_id' => $item['product_categorie_id'],
                            'warehouse_id' => 1,
                            'unit_id' => 1,
                            'supplier_id' => $request->supplier_id,
                            'price' => $item['unit_price'] * 1.55,
                            'price_sale' => $item['unit_price'] * 1.55,
                            'purchase_price' => $item['unit_price'],
                            'tax_rate' => 15, // Asumiendo IVA general
                            'max_discount' => 0,
                            'discount_percentage' => 0,
                            'brand' => 'Genérico',
                            'stock' => $item['quantity'],
                            'item_type' => $item['item_type'],
                            'min_stock' => 1,
                            'max_stock' => 5,
                            'is_taxable' => 1,
                            'is_gift' => 2,
                            'notes' => 'Creado automáticamente por Compra Manual: ' . $request->invoice_number,
                            'state' => 1,
                        ]);
                    } else {
                        // Incrementar el stock y actualizar el costo
                        $product->stock += $item['quantity'];
                        $product->purchase_price = $item['unit_price']; // Actualiza el precio de costo a la compra más reciente
                        $product->save();
                    }
                }
            }

            // 3. Financial Integration
            if ($request->payment_type === 'efectivo' || $request->payment_type === 'aporte') {
                $supplier = \App\Models\Supplier\Supplier::find($request->supplier_id);
                $supplierName = $supplier ? ($supplier->trade_name ?? $supplier->name) : ('#' . $request->supplier_id);
                $financeRecord = new FinanceRecord([
                    'type' => FinanceRecord::TYPE_EXPENSE,
                    'amount' => $request->total,
                    'invoice_number' => $request->invoice_number,
                    'description' => 'Pago por Compra Manual a Proveedor ' . $supplierName . ' - Costo de Ventas',
                    'user_id' => auth()->id() ?? 1,
                ]);

                $accountId = null;
                $paymentMethod = 'cash'; // Default

                if ($request->payment_type === 'efectivo') {
                    if (!$request->account_id) {
                        throw new \Exception('Se requiere cuenta para pago en efectivo.');
                    }
                    $accountId = $request->account_id;
                    $financeRecord->account_id = $accountId;
                    // Se aplica la lógica de payment_method basada en la cuenta en el boot() de FinanceRecord
                } else if ($request->payment_type === 'aporte') {
                    if (!$request->partner_id) {
                        throw new \Exception('Se requiere seleccionar un socio para el pago con aporte.');
                    }

                    // Buscar la cuenta ligada a los aportes de capital del socio
                    $aporte = AporteCapital::where('partner_id', $request->partner_id)->latest()->first();
                    if (!$aporte || !$aporte->cuenta_id) {
                        throw new \Exception('El socio seleccionado no tiene aportes de capital ni cuenta asociada.');
                    }
                    $accountId = $aporte->cuenta_id;
                    $financeRecord->account_id = $accountId;
                    $financeRecord->description .= ' (Financiado por Aporte de Socio)';
                }

                $financeRecord->save();

                // Registrar distribución de pagos para que funcione getCurrentBalanceAttribute
                $distribution = PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => $request->total,
                    'payment_method' => $financeRecord->payment_method ?? 'cash',
                ]);

                // Registrar movimiento financiero para el dashboard y actualizar saldo de la cuenta
                $distribution->registerMovement(
                    $accountId,
                    'expense',
                    $request->total,
                    $financeRecord->description,
                    Carbon::now('America/Guayaquil')->format('Y-m-d'),
                    [
                        'finance_record_id' => $financeRecord->id,
                        'record_type' => 1, // expense
                        'invoice' => $financeRecord->invoice_number,
                    ]
                );

                $account = \App\Models\Finance\Account::find($accountId);
                if ($account) {
                    $account->updateBalance($request->total, 1); // 1 = Egreso
                }

            } else if ($request->payment_type === 'credito') {
                AccountPayable::create([
                    'supplier_id' => $request->supplier_id,
                    'invoice_id' => $invoice->id,
                    'total_amount' => $request->total,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'due_date' => Carbon::now()->addDays(30), // Por defecto 30 días
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Compra manual registrada con éxito',
                'data' => $invoice->load('invoice_items', 'supplier')
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar la compra manual',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

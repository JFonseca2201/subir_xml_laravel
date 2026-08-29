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
            'supplier_id' => 'nullable',
            'supplier_ruc' => 'nullable|string|max:20',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_address' => 'nullable|string|max:500',
            'invoice_number' => 'required',
            'issue_date' => 'required',
            'payment_type' => 'required|string|in:efectivo,credito,aporte',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'access_key' => 'nullable',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'is_shared' => 'nullable|boolean',
            'terceros_total' => 'nullable|numeric|min:0',
            'taller_total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.code' => 'required',
            'items.*.description' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.item_type' => 'required',
            'items.*.product_categorie_id' => 'nullable',
            'items.*.brand' => 'nullable',
        ]);

        DB::beginTransaction();

        try {
            // Resolver Proveedor (Existente por ID o por RUC, o crear nuevo)
            $supplierId = null;
            if ($request->supplier_id && is_numeric($request->supplier_id)) {
                $existing = \App\Models\Supplier\Supplier::find($request->supplier_id);
                if ($existing) {
                    $supplierId = $existing->id;
                }
            }

            if (!$supplierId) {
                $ruc = $request->supplier_ruc ?: ($request->supplier_id ? (string) $request->supplier_id : '9999999999001');
                $name = $request->supplier_name ?: 'PROVEEDOR ' . $ruc;
                $supplier = \App\Models\Supplier\Supplier::firstOrCreate(
                    ['ruc' => $ruc],
                    [
                        'tax_id' => $ruc,
                        'name' => $name,
                        'address' => $request->supplier_address ?: 'S/N',
                    ]
                );
                $supplierId = $supplier->id;
            }

            // 1. Validar duplicados de Factura (Clave de Acceso y Número de Factura)
            if (!empty($request->access_key)) {
                $duplicateAccess = Invoice::where('access_key', $request->access_key)->first();
                if ($duplicateAccess) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Esta factura ya fue ingresada anteriormente en el sistema (Clave de Acceso ya registrada).',
                    ], 422);
                }
            }

            $duplicateNumber = Invoice::where('invoice_number', $request->invoice_number)
                ->where('supplier_id', $supplierId)
                ->first();
            if ($duplicateNumber) {
                return response()->json([
                    'status' => 422,
                    'message' => 'La factura N° ' . $request->invoice_number . ' ya existe registrada para este proveedor.',
                ], 422);
            }

            // 2. Create the Invoice (Purchase)
            $accessKey = $request->access_key ?: ('MANUAL-' . strtoupper(Str::random(10)) . '-' . time());
            $issueDate = Carbon::parse($request->issue_date);

            $invoice = Invoice::create([
                'supplier_id' => $supplierId,
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

            // Obtener defaults para productos si no existen
            $defaultWarehouseId = \App\Models\Config\Warehouse::first()?->id ?? 1;
            $defaultUnitId = \App\Models\Config\Unit::first()?->id ?? 1;
            $defaultCatId = \App\Models\Config\ProductCategorie::first()?->id ?? 1;

            // 3. Create Invoice Items & Update Stock
            foreach ($request->items as $item) {
                $isProduct = (int) $item['item_type'] === 1;
                $itemCatId = $isProduct ? ($item['product_categorie_id'] ?? $defaultCatId) : null;

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
                    'product_categorie_id' => $itemCatId,
                ]);

                // Update or Create Product Stock solo si es producto y pertenece al inventario del taller
                $selectedForInventory = !isset($item['selected_for_inventory']) || $item['selected_for_inventory'] === true || $item['selected_for_inventory'] === 1 || $item['selected_for_inventory'] === '1' || $item['selected_for_inventory'] === 'true';
                if ($isProduct && $selectedForInventory) { // 1 = Producto Físico del taller
                    $product = Product::where('sku', $item['code'])
                        ->orWhere('description', $item['description'])
                        ->first();

                    $brand = !empty($item['brand']) ? trim($item['brand']) : 'Genérico';

                    if (!$product) {
                        Product::create([
                            'description' => $item['description'],
                            'sku' => $item['code'],
                            'product_categorie_id' => $itemCatId,
                            'warehouse_id' => $defaultWarehouseId,
                            'unit_id' => $defaultUnitId,
                            'supplier_id' => $supplierId,
                            'price' => $item['unit_price'] * 1.55,
                            'price_sale' => $item['unit_price'] * 1.55,
                            'purchase_price' => $item['unit_price'],
                            'tax_rate' => 15, // Asumiendo IVA general
                            'max_discount' => 0,
                            'discount_percentage' => 0,
                            'brand' => $brand,
                            'stock' => $item['quantity'],
                            'item_type' => $item['item_type'],
                            'min_stock' => 1,
                            'max_stock' => 5,
                            'is_taxable' => 1,
                            'is_gift' => 2,
                            'notes' => 'Creado por Compra: ' . $request->invoice_number,
                            'state' => 1,
                        ]);
                    } else {
                        // Incrementar el stock y actualizar el costo y marca/categoría si aplica
                        $product->stock += $item['quantity'];
                        $product->purchase_price = $item['unit_price']; // Actualiza el precio de costo a la compra más reciente
                        if (!empty($item['brand'])) {
                            $product->brand = $brand;
                        }
                        if (!empty($item['product_categorie_id'])) {
                            $product->product_categorie_id = $item['product_categorie_id'];
                        }
                        $product->save();
                    }
                }
            }

            // 4. Financial Integration
            $expenseAmount = $request->is_shared ? (float) ($request->taller_total ?? ($request->total - ($request->terceros_total ?? 0))) : (float) $request->total;

            if ($request->payment_type === 'efectivo' || $request->payment_type === 'aporte') {
                $supplier = \App\Models\Supplier\Supplier::find($supplierId);
                $supplierName = $supplier ? ($supplier->trade_name ?? $supplier->name) : ('#' . $supplierId);
                $desc = 'Pago por Compra a Proveedor ' . $supplierName . ($request->is_shared ? ' [Factura Compartida: Taller $' . number_format($expenseAmount, 2) . ' / Total $' . number_format($request->total, 2) . ']' : '') . ' - Costo de Ventas';

                $accountId = null;
                if ($request->payment_type === 'efectivo') {
                    if (!$request->account_id) {
                        throw new \Exception('Se requiere cuenta para pago en efectivo.');
                    }
                    $accountId = $request->account_id;
                } else if ($request->payment_type === 'aporte') {
                    if (!$request->partner_id) {
                        throw new \Exception('Se requiere seleccionar un socio para el pago con aporte.');
                    }

                    // Buscar la cuenta ligada a los aportes de capital del socio
                    $aporte = AporteCapital::where('partner_id', $request->partner_id)->latest()->first();
                    $accountId = $aporte?->cuenta_id ?? \App\Models\Finance\Account::first()?->id ?? 1;
                    $desc .= ' (Financiado por Aporte de Socio)';
                }

                $financeRecord = new FinanceRecord([
                    'type' => FinanceRecord::TYPE_EXPENSE,
                    'account_id' => $accountId,
                    'amount' => $expenseAmount,
                    'invoice_number' => $request->invoice_number,
                    'description' => $desc,
                    'user_id' => auth()->id() ?? 1,
                ]);

                $financeRecord->save();

                // Registrar distribución de pagos para que funcione getCurrentBalanceAttribute
                $distribution = PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => $expenseAmount,
                    'payment_method' => $financeRecord->payment_method ?? 'cash',
                ]);

                // Registrar movimiento financiero para el dashboard y actualizar saldo de la cuenta
                $distribution->registerMovement(
                    $accountId,
                    'expense',
                    $expenseAmount,
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
                    $account->updateBalance($expenseAmount, 1); // 1 = Egreso
                }

            } else if ($request->payment_type === 'credito') {
                AccountPayable::create([
                    'supplier_id' => $supplierId,
                    'invoice_id' => $invoice->id,
                    'total_amount' => $expenseAmount,
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
            \Log::error('Error al guardar compra manual: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar la compra: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sales\Sale;
use App\Models\Sales\ProductReturn;
use App\Models\Product\Product as ModelsProduct;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\Account;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductReturnController extends Controller
{
    /**
     * Display a listing of returns.
     */
    public function index(Request $request)
    {
        try {
            $query = ProductReturn::with(['sale', 'user']);

            if ($request->has('search') && $request->search != '') {
                $searchTerm = $request->search;
                $query->where('return_number', 'like', "%{$searchTerm}%")
                      ->orWhereHas('sale', function ($q) use ($searchTerm) {
                          $q->where('document_number', 'like', "%{$searchTerm}%");
                      });
            }

            $returns = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'success' => true,
                'data'    => $returns
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las devoluciones.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created return in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sale_id'       => 'required|exists:sales,id',
            'type'          => 'required|in:total,partial',
            'reason'        => 'required|string',
            'refund_amount' => 'required|numeric|min:0',
            'account_id'    => 'nullable|exists:accounts,id', // Cuenta de donde sale el dinero si se devuelve en efectivo
            'items'         => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.price'      => 'required|numeric',
        ]);

        try {
            $sale = Sale::with('details')->findOrFail($request->sale_id);

            // Validar que no se devuelvan más items de los vendidos originalmente
            foreach ($request->items as $item) {
                // Buscar el item original por product_id y description
                $originalItem = $sale->details->first(function ($detail) use ($item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        return $detail->product_id == $item['product_id'];
                    }
                    return $detail->description == $item['description'];
                });

                if (!$originalItem) {
                    return response()->json([
                        'success' => false,
                        'message' => "El producto '{$item['description']}' no pertenece a esta venta."
                    ], 400);
                }

                if ($item['quantity'] > $originalItem->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "La cantidad a devolver de '{$item['description']}' ({$item['quantity']}) excede la cantidad vendida ({$originalItem->quantity})."
                    ], 400);
                }
            }

            $productReturn = DB::transaction(function () use ($request, $sale) {

                // 1. Crear la Devolución
                // Generar número de devolución DEV-XXXX
                $lastReturn = ProductReturn::latest('id')->first();
                $nextId = $lastReturn ? $lastReturn->id + 1 : 1;
                $returnNumber = 'DEV-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                $return = ProductReturn::create([
                    'return_number' => $returnNumber,
                    'sale_id'       => $sale->id,
                    'user_id'       => $request->user_id ?? auth()->id() ?? 1,
                    'type'          => $request->type,
                    'refund_amount' => $request->refund_amount,
                    'reason'        => $request->reason,
                ]);

                // 2. Registrar Detalles y Devolver Stock
                foreach ($request->items as $item) {
                    $itemTotal = $item['quantity'] * $item['price'];

                    $return->details()->create([
                        'product_id'  => $item['product_id'] ?? null,
                        'description' => $item['description'] ?? 'Producto devuelto',
                        'quantity'    => $item['quantity'],
                        'price'       => $item['price'],
                        'total'       => $itemTotal,
                    ]);

                    // Deducir stock (reincorporar inventario)
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product) {
                            $product->stock += $item['quantity'];
                            $product->save();
                        }
                    }

                    // Remover o reducir el item de la venta original
                    $originalItem = $sale->details->first(function ($detail) use ($item) {
                        if (isset($item['product_id']) && $item['product_id']) {
                            return $detail->product_id == $item['product_id'];
                        }
                        return $detail->description == $item['description'];
                    });

                    if ($originalItem) {
                        // Calcular descuento proporcional a la cantidad que queda
                        $oldQuantity = $originalItem->quantity;
                        $newQuantity = $oldQuantity - $item['quantity'];
                        
                        if ($newQuantity <= 0) {
                            $originalItem->delete();
                        } else {
                            $discountPerUnit = $originalItem->discount / $oldQuantity;
                            $originalItem->quantity = $newQuantity;
                            $originalItem->discount = $discountPerUnit * $newQuantity;
                            $originalItem->total = ($newQuantity * $originalItem->price) - $originalItem->discount;
                            $originalItem->save();
                        }
                    }
                }

                // Recalcular los totales de la venta original
                // Necesitamos volver a cargar los detalles frescos desde la BD porque algunos fueron borrados o actualizados
                $sale->load('details');
                
                $subtotal = $sale->details->sum('total');
                $taxAmount = $sale->document_type === 'invoice' ? $subtotal * 0.15 : 0;
                $total = $subtotal + $taxAmount;

                $sale->subtotal = $subtotal;
                $sale->tax_amount = $taxAmount;
                $sale->total = $total;

                // 3. Lógica Financiera (Caja / Cuentas)
                if ($request->refund_amount > 0) {
                    // Si la venta fue pagada o tiene abonos, retiramos el dinero de la cuenta
                    if (in_array($sale->payment_status, ['paid', 'partial'])) {
                        
                        $accountId = $request->account_id ?? 1; // Default a Caja Chica
                        
                        // Crear el registro de egreso (EXPENSE)
                        $financeRecord = FinanceRecord::create([
                            'entry_date'        => now()->format('Y-m-d'),
                            'type'              => FinanceRecord::TYPE_EXPENSE,
                            'account_id'        => $accountId,
                            'payment_method'    => $accountId == 1 ? 'cash' : 'transfer',
                            'amount'            => $request->refund_amount,
                            'work_order_number' => $sale->document_number,
                            'invoice_number'    => $returnNumber,
                            'description'       => 'Devolución de Venta: ' . $sale->document_number . ' - ' . $request->reason,
                            'user_id'           => $request->user_id ?? auth()->id() ?? 1,
                        ]);

                        // Crear distribución de pago
                        PaymentDistribution::create([
                            'finance_record_id' => $financeRecord->id,
                            'account_id'        => $accountId,
                            'amount'            => $request->refund_amount,
                            'payment_method'    => $accountId == 1 ? 'cash' : 'transfer',
                        ]);

                        // Actualizar el saldo de la cuenta
                        $account = Account::find($accountId);
                        if ($account) {
                            $account->updateBalance($request->refund_amount, FinanceRecord::TYPE_EXPENSE);
                        }

                        // Registrar movimiento financiero asociado a la devolución
                        if (method_exists($return, 'registerMovement')) {
                            $return->registerMovement(
                                $accountId,
                                'expense',
                                $request->refund_amount,
                                'Devolución de Venta: ' . $sale->document_number,
                                now()->format('Y-m-d'),
                                [
                                    'document_number' => $returnNumber,
                                    'finance_record_id' => $financeRecord->id,
                                ]
                            );
                        }
                    } 
                }

                // 4. Marcar la venta como que tiene devoluciones
                $sale->has_returns = true;
                $sale->save();

                return $return;
            });

            return response()->json([
                'success' => true,
                'message' => 'La devolución se ha procesado correctamente.',
                'data'    => $productReturn->load('details')
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la devolución.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified return.
     */
    public function show($id)
    {
        try {
            $return = ProductReturn::with(['details.product', 'sale', 'user'])->find((int)$id);

            if (!$return) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de devolución no existe.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $return
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle de la devolución.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

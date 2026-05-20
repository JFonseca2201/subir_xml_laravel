<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Sale;
use App\Models\Product;
use App\Models\FinanceRecord;
use App\Models\PaymentDistribution;
use App\Models\Account;
use App\Models\Product\Product as ModelsProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Listar el historial de ventas y cotizaciones con filtros.
     */
    public function index(Request $request)
    {
        try {
            // Iniciamos la consulta cargando las relaciones clave (Eager Loading)
            // Esto evita el problema de consultas N+1 y hace que la API vuele
            $query = Sale::with(['client', 'vehicle', 'user']);

            // 1. Filtro por búsqueda (nombre, cédula del cliente o placa de vehículo)
            if ($request->has('search') && $request->search != '') {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereHas('client', function ($clientQuery) use ($searchTerm) {
                        $clientQuery->where('full_name', 'like', "%{$searchTerm}%")
                            ->orWhere('n_document', 'like', "%{$searchTerm}%");
                    })
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($searchTerm) {
                            $vehicleQuery->where('license_plate', 'like', "%{$searchTerm}%");
                        });
                });
            }

            // 2. Filtro por tipo de documento (quote, sale_note, invoice)
            if ($request->has('document_type') && $request->document_type != '') {
                $query->where('document_type', $request->document_type);
            }

            // 3. Filtro por cliente específico
            if ($request->has('client_id') && $request->client_id != '') {
                $query->where('client_id', $request->client_id);
            }

            // 4. Filtro por rango de fechas de atención (Muy útil para cierres de caja)
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            }

            // 5. Filtro por estado de pago (paid, partial, pending)
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            // Ordenamos para que las más recientes salgan primerito
            // Paginamos de 15 en 15 para que la pantalla del frente cargue al instante
            $sales = $query->orderBy('service_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data'    => $sales
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de ventas.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validación estricta de los datos que vienen del Vue 3
        $request->validate([
            'document_type'   => 'required|in:quote,sale_note,invoice',
            'document_number' => 'required|string|unique:sales,document_number',
            'client_id'       => 'required|exists:clients,id',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
            'mileage'         => 'nullable|integer',
            'service_date'    => 'nullable|date',
            'subtotal'        => 'required|numeric',
            'tax_amount'      => 'required|numeric',
            'total'           => 'required|numeric',
            'payment_status'  => 'required|in:paid,partial,pending',
            'is_credited'     => 'required|boolean',
            'payment_method'  => 'required|string',
            'observations'    => 'nullable|string',
            'items'           => 'required|array|min:1', // El carrito no puede estar vacío
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.price'       => 'required|numeric',
            'items.*.discount'    => 'required|numeric',
            'payment_distributions' => 'nullable|array', // Pagos distribuidos entre diferentes cuentas
            'payment_distributions.*.account_id' => 'required|exists:accounts,id',
            'payment_distributions.*.amount' => 'required|numeric|min:0',
            'payment_distributions.*.payment_method' => 'required|string',
        ]);

        try {
            // 2. Iniciamos la transacción para asegurar consistencia atómica
            $sale = DB::transaction(function () use ($request) {

                // A. Crear la cabecera de la venta
                $sale = Sale::create([
                    'document_type'   => $request->document_type,
                    'document_number' => $request->document_number,
                    'client_id'       => $request->client_id,
                    'vehicle_id'      => $request->vehicle_id,
                    'user_id'         => $request->user_id,
                    'mileage'         => $request->mileage,
                    'service_date'    => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal'        => $request->subtotal,
                    'tax_amount'      => $request->tax_amount,
                    'total'           => $request->total,
                    'status'          => $request->document_type === 'quote' ? 'pending' : 'completed',
                    'payment_status'  => $request->payment_status,
                    'is_credited'     => $request->is_credited,
                    'payment_method'  => $request->payment_method,
                    'observations'    => $request->observations,
                ]);

                // B. Registrar cada producto/servicio del detalle
                foreach ($request->items as $item) {
                    $sale->details()->create([
                        'product_id'  => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity'    => $item['quantity'],
                        'price'       => $item['price'],
                        'discount'    => $item['discount'] ?? 0.00,
                        'total'       => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0.00),
                    ]);

                    // 💥 ESPACIO RESERVADO LUNES:
                    // Si $request->document_type !== 'quote', aquí disparamos la resta de Stock
                }

                // 💰 ESCALABILIDAD FINANCIERA: Actualizar cuentas según métodos de pago
                // Si $request->document_type !== 'quote' (Es Nota de Venta o Factura)
                if ($request->document_type !== 'quote') {
                    $this->processFinancialRecord($sale, $request);
                }

                return $sale;
            });

            // 3. Respuesta exitosa al frontend con el registro completo cargando sus detalles
            return response()->json([
                'success' => true,
                'message' => 'El registro se procesó correctamente.',
                'data'    => $sale->load('details')
            ], 201);
        } catch (Exception $e) {
            // Si algo truena dentro del bloque, el DB::transaction hace rollback automático
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process financial record for sale and update accounts
     */
    private function processFinancialRecord($sale, Request $request)
    {
        //$userId = auth()->id() ?? 1;

        // Crear el registro financiero principal
        $financeRecord = FinanceRecord::create([
            'entry_date' => $sale->service_date ?? now()->format('Y-m-d'),
            'type' => FinanceRecord::TYPE_INCOME,
            'account_id' => 1, // Default, se sobrescribe con payment_distributions
            'payment_method' => $request->payment_method,
            'amount' => $request->total,
            'work_order_number' => $request->document_number,
            'invoice_number' => $request->document_number,
            'description' => 'Venta: ' . $request->document_type . ' - ' . $request->document_number,
            'user_id' => $request->user_id,
        ]);

        // Procesar pagos distribuidos si existen
        if ($request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0) {
            foreach ($request->payment_distributions as $distribution) {
                // Crear la distribución de pago
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $distribution['account_id'],
                    'amount' => $distribution['amount'],
                    'payment_method' => $distribution['payment_method'],
                ]);

                // Actualizar el saldo de la cuenta correspondiente
                $account = Account::find($distribution['account_id']);
                if ($account) {
                    $account->updateBalance($distribution['amount'], FinanceRecord::TYPE_INCOME);
                }

                // Registrar movimiento financiero en financial_movements
                $sale->registerMovement(
                    $distribution['account_id'],
                    'income',
                    $distribution['amount'],
                    'Venta: ' . $request->document_type . ' - ' . $request->document_number . ' - ' . $distribution['payment_method'],
                    $sale->service_date ?? now()->format('Y-m-d'),
                    [
                        'document_type' => $request->document_type,
                        'document_number' => $request->document_number,
                        'payment_method' => $distribution['payment_method'],
                        'finance_record_id' => $financeRecord->id,
                    ]
                );
            }
        } else {
            // Si no hay pagos distribuidos, usar el método de pago único
            // Determinar la cuenta según el método de pago
            $accountId = 1; // Default: Caja chica (Efectivo)
            if (strtolower($request->payment_method) === 'transferencia' || strtolower($request->payment_method) === 'transfer') {
                $accountId = 2; // Banco Pichincha (default para transferencias)
            }

            // Crear distribución de pago única
            PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $accountId,
                'amount' => $request->total,
                'payment_method' => $request->payment_method,
            ]);

            // Actualizar el saldo de la cuenta

            $account = Account::where($accountId)->first();
            if ($account) {
                $account->updateBalance($request->total, FinanceRecord::TYPE_INCOME);
            }

            // Registrar movimiento financiero en financial_movements
            $sale->registerMovement(
                $accountId,
                'income',
                $request->total,
                'Venta: ' . $request->document_type . ' - ' . $request->document_number . ' - ' . $request->payment_method,
                $sale->service_date ?? now()->format('Y-m-d'),
                [
                    'document_type' => $request->document_type,
                    'document_number' => $request->document_number,
                    'payment_method' => $request->payment_method,
                    'finance_record_id' => $financeRecord->id,
                ]
            );
        }

        // Si es crédito ('is_credited' => true), registramos solo el abono inicial si lo hay
        // La lógica de pagos parciales se maneja con payment_status = 'partial'
    }

    /**
     * Generate PDF report for sales
     */
    public function generatePDF(Request $request)
    {
        try {
            // Aplicar los mismos filtros que en index
            $query = Sale::with(['client', 'vehicle', 'user', 'details']);

            // Filtro por búsqueda (nombre, cédula del cliente o placa de vehículo)
            if ($request->has('search') && $request->search != '') {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereHas('client', function ($clientQuery) use ($searchTerm) {
                        $clientQuery->where('full_name', 'like', "%{$searchTerm}%")
                            ->orWhere('n_document', 'like', "%{$searchTerm}%");
                    })
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($searchTerm) {
                            $vehicleQuery->where('license_plate', 'like', "%{$searchTerm}%");
                        });
                });
            }

            // Filtro por tipo de documento
            if ($request->has('document_type') && $request->document_type != '') {
                $query->where('document_type', $request->document_type);
            }

            // Filtro por cliente específico
            if ($request->has('client_id') && $request->client_id != '') {
                $query->where('client_id', $request->client_id);
            }

            // Filtro por rango de fechas
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            }

            // Filtro por estado de pago
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            // Obtener todos los resultados sin paginación para el PDF
            $sales = $query->orderBy('service_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // Generar PDF usando DOMPDF o similar
            // Por ahora, retornamos una respuesta simple indicando que se necesita instalar la librería
            return response()->json([
                'success' => false,
                'message' => 'Para generar PDF'
            ], 501);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF for a single sale
     */
    public function generateSinglePDF(int $id)
    {
        try {
            $sale = Sale::with(['client', 'vehicle', 'user', 'details', 'financeRecord.paymentDistributions.account'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            $pdf = Pdf::loadView('sales.pdf', compact('sale'));
            return $pdf->stream($sale->document_type . '_' . $sale->document_number . '.pdf');
        } catch (Exception $e) {
            return response()->json([

                'success' => false,
                'message' => 'Error al generar el PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    /**
     * Ver el detalle completo de una sola venta o cotización (Para cargar en el frente).
     */
    public function show($id)
    {
        try {
            // Buscamos la venta cargando al mismo tiempo sus detalles, el cliente, el vehículo y los registros financieros con pagos distribuidos y cuentas
            $sale = Sale::with(['details', 'client', 'vehicle', 'financeRecord.paymentDistributions.account'])->find((int)$id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $sale
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle de la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * Actualizar datos permitidos de una venta o cotización (El procesamiento del Edit).
     */
    public function update(Request $request, int $id)
    {
        // 1. Validamos los campos que se pueden editar
        $request->validate([
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'mileage'      => 'nullable|integer',
            'service_date' => 'nullable|date',
            'observations' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'document_type' => 'nullable|in:quote,sale_note,invoice',
            'payment_status' => 'nullable|in:paid,partial,pending',
            'is_credited' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|exists:sale_details,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'payment_distributions' => 'nullable|array',
            'payment_distributions.*.account_id' => 'required|exists:accounts,id',
            'payment_distributions.*.amount' => 'required|numeric|min:0',
            'payment_distributions.*.payment_method' => 'required|string',
        ]);

        try {
            $sale = Sale::with(['details', 'financeRecord.paymentDistributions'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro no existe.'
                ], 404);
            }

            // Regla de seguridad: Si la venta ya está anulada, no debería editarse
            if ($sale->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una venta que ya ha sido anulada.'
                ], 400);
            }

            // Regla: Solo se puede convertir de cotización a venta, no al revés
            if ($request->has('document_type') && $request->document_type !== $sale->document_type) {
                if ($sale->document_type !== 'quote') {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede cambiar el tipo de documento de una venta o factura. Solo las cotizaciones pueden convertirse en ventas.'
                    ], 400);
                }
            }

            // Verificar si se está convirtiendo de cotización a venta
            $wasQuote = $sale->document_type === 'quote';
            $isNowSale = $request->has('document_type') && $request->document_type !== 'quote';

            // Actualizar campos operativos básicos
            $sale->update($request->only([
                'vehicle_id',
                'mileage',
                'service_date',
                'observations',
                'payment_method',
                'document_type',
                'payment_status',
                'is_credited'
            ]));

            // Si se proporcionan items, actualizar el detalle
            if ($request->has('items')) {
                DB::transaction(function () use ($sale, $request, $wasQuote, $isNowSale) {
                    // Obtener IDs de los items enviados
                    $itemIds = array_filter(array_column($request->items, 'id'));

                    // Eliminar items que no están en la solicitud
                    $sale->details()->whereNotIn('id', $itemIds)->delete();

                    // Actualizar o crear items
                    foreach ($request->items as $item) {
                        $itemTotal = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                        if (isset($item['id'])) {
                            // Actualizar item existente
                            $sale->details()->where('id', $item['id'])->update([
                                'product_id' => $item['product_id'] ?? null,
                                'description' => $item['description'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                                'discount' => $item['discount'] ?? 0,
                                'total' => $itemTotal,
                            ]);
                        } else {
                            // Crear nuevo item
                            $sale->details()->create([
                                'product_id' => $item['product_id'] ?? null,
                                'description' => $item['description'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                                'discount' => $item['discount'] ?? 0,
                                'total' => $itemTotal,
                            ]);
                        }
                    }

                    // Recalcular totales
                    $subtotal = $sale->details()->sum('total');
                    $taxAmount = $sale->document_type === 'invoice' ? $subtotal * 0.15 : 0;
                    $total = $subtotal + $taxAmount;

                    $sale->update([
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total' => $total,
                    ]);

                    // Si se convierte de cotización a venta, procesar el stock y finanzas
                    if ($wasQuote && $isNowSale) {
                        $sale->status = 'completed';
                        $sale->save();

                        // Restar stock de los productos
                        foreach ($sale->details as $detail) {
                            if ($detail->product_id) {
                                $product = ModelsProduct::find($detail->product_id);
                                if ($product) {
                                    $product->stock -= $detail->quantity;
                                    $product->save();
                                }
                            }
                        }

                        // Procesar registro financiero
                        $this->processFinancialRecord($sale, $request);
                    }
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'El registro fue actualizado correctamente.',
                'data'    => $sale->load('details')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            // Buscamos la venta maestra
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            // Si ya está anulada, no hacemos nada
            if ($sale->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta ya se encuentra anulada.'
                ], 400);
            }

            DB::transaction(function () use ($sale) {
                // A. Cambiamos el estado a cancelado
                $sale->update([
                    'status' => 'canceled'
                ]);

                // � ESCALABILIDAD FINANCIERA: Reversar movimientos de cuentas
                // Si la venta tiene un registro financiero, revertimos los pagos distribuidos
                if ($sale->financeRecord) {
                    $financeRecord = $sale->financeRecord;

                    // Revertir cada distribución de pago
                    foreach ($financeRecord->paymentDistributions as $distribution) {
                        // Actualizar el saldo de la cuenta (restando el ingreso)
                        $account = Account::find($distribution->account_id);
                        if ($account) {
                            $account->updateBalance($distribution->amount, FinanceRecord::TYPE_EXPENSE); // Usar expense para restar
                        }
                    }

                    // Opcional: Eliminar o marcar el registro financiero como anulado
                    // $financeRecord->delete();
                }

                // �� ESPACIO RESERVADO LUNES: ESCALABILIDAD
                // Aquí disparamos la reversa de Stock para cada producto/servicio del detalle
                // $sale->details()->each(function($detail) {
                //     // Lógica para sumar nuevamente el stock del producto
                // });
            });

            return response()->json([
                'success' => true,
                'message' => 'La venta ha sido anulada correctamente y el estado fue actualizado.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

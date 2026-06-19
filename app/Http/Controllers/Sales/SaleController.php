<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Sale;
use App\Models\Product;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\Account;
use App\Models\Product\Product as ModelsProduct;
use App\Services\SequenceService;
use App\Services\WorkOrder\WorkOrderSaleSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Mail;

class SaleController extends Controller
{
    /**
     * Get next sequence number
     */
    public function getNextNumber()
    {
        return response()->json([
            'success' => true,
            'data' => SequenceService::previewNextDirectSaleNumber()
        ]);
    }

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
            $query = Sale::with(['client', 'vehicle', 'user', 'workOrder', 'financeRecord.paymentDistributions']);

            // 1. Filtro por búsqueda (nombre, cédula del cliente, placa de vehículo o número de documento)
            if ($request->has('search') && $request->search != '') {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('document_number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('client', function ($clientQuery) use ($searchTerm) {
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
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('service_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('service_date', '<=', $request->end_date);
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
                'data' => $sales
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de ventas.',
                'error' => $e->getMessage()
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
            'document_type' => 'required|in:quote,sale_note,invoice',
            'document_number' => 'required|string|unique:sales,document_number',
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'mileage' => 'nullable|integer',
            'service_date' => 'nullable|date',
            'subtotal' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'total' => 'required|numeric',
            'payment_status' => 'required|in:paid,partial,pending',
            'is_credited' => 'required|boolean',
            'payment_method' => 'required|string',
            'observations' => 'nullable|string',
            'items' => 'required|array|min:1', // El carrito no puede estar vacío
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'payment_distributions' => 'nullable|array', // Pagos distribuidos entre diferentes cuentas
            'payment_distributions.*.account_id' => 'required|exists:accounts,id',
            'payment_distributions.*.amount' => 'required|numeric|min:0',
            'payment_distributions.*.payment_method' => 'required|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
            'is_draft' => 'nullable|boolean',
        ]);

        try {
            $linkedWorkOrder = null;
            if ($request->work_order_id) {
                $linkedWorkOrder = WorkOrderSaleSync::assertReadyForInvoicing((int) $request->work_order_id);
                $request->merge(['document_number' => $linkedWorkOrder->number]);
            }

            $isDraft = $request->boolean('is_draft');

            // Validar pagos distribuidos solo si no es cotización y no es borrador
            if ($request->document_type !== 'quote' && !$isDraft) {
                $hasDistributions = $request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0;

                if (!$hasDistributions && !$request->boolean('is_credited')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debe registrar al menos un pago para la venta.',
                        'error' => 'validation_error'
                    ], 400);
                }

                $totalDist = $hasDistributions ? array_sum(array_column($request->payment_distributions, 'amount')) : 0;

                if ($totalDist <= 0 && !$request->boolean('is_credited')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debe registrar al menos un pago para la venta.',
                        'error' => 'validation_error'
                    ], 400);
                }

                if ($totalDist > $request->total + 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La suma de los pagos no puede ser mayor al total.',
                        'error' => 'validation_error'
                    ], 400);
                }

                if (abs($totalDist - $request->total) <= 0.01) {
                    $paymentStatus = 'paid';
                } elseif ($totalDist > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'pending';
                }

                $request->merge(['payment_status' => $paymentStatus]);
            }

            // 2. Validar stock antes de procesar la venta (solo si no es cotización y es producto físico)
            if ($request->document_type !== 'quote' && !$isDraft) {
                foreach ($request->items as $item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product && $product->item_type == 1 && $product->stock < $item['quantity']) {
                            return response()->json([
                                'success' => false,
                                'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                                'error' => 'stock_insufficient'
                            ], 400);
                        }
                    }
                }
            }

            // 3. Validar descuentos y margen (sólo aplica para item_type == 1 / Productos Físicos)
            foreach ($request->items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1) {
                        // A. Validar que el precio final no sea menor al precio de compra (purchase_price)
                        $itemDiscount = (float) ($item['discount'] ?? 0.00);
                        $finalPrice = ($item['quantity'] * $item['price']) - $itemDiscount;
                        $minFinalPrice = $item['quantity'] * ($product->purchase_price ?? 0.00);
                        if ($finalPrice < $minFinalPrice) {
                            return response()->json([
                                'success' => false,
                                'message' => "El descuento excede el margen permitido para el producto: {$product->description}. El precio final no puede ser menor al costo de compra (\${$product->purchase_price} c/u).",
                                'error' => 'price_below_cost'
                            ], 400);
                        }

                        // B. Validar porcentaje de descuento máximo definido
                        if ($product->discount_percentage > 0) {
                            $maxAllowedByPct = ($item['quantity'] * $item['price']) * ($product->discount_percentage / 100);
                            if ($itemDiscount > $maxAllowedByPct) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "El descuento excede el porcentaje máximo permitido ({$product->discount_percentage}%) para el producto: {$product->description}.",
                                    'error' => 'discount_exceeded'
                                ], 400);
                            }
                        }

                        // C. Validar max_discount (monto absoluto o porcentaje según lógica del sistema)
                        if ($product->max_discount > 0) {
                            $maxAllowedByVal = ($item['quantity'] * $item['price']) * ($product->max_discount / 100);
                            if ($itemDiscount > $maxAllowedByVal) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "El descuento excede el máximo permitido para el producto: {$product->description}.",
                                    'error' => 'discount_exceeded'
                                ], 400);
                            }
                        }
                    }
                }
            }

            $paymentMethod = $this->resolveSalePaymentMethod($request);

            // 4. Iniciamos la transacción para asegurar consistencia atómica
            $sale = DB::transaction(function () use ($request, $linkedWorkOrder, $paymentMethod, $isDraft) {

                // Consumir el número de documento de forma segura solo si NO viene de una orden de trabajo
                if ($linkedWorkOrder) {
                    $documentNumber = $linkedWorkOrder->number;
                } else {
                    $documentNumber = \App\Services\SequenceService::consumeGlobalNumber($request->document_number);
                }

                // A. Crear la cabecera de la venta
                $sale = Sale::create([
                    'document_type' => $request->document_type,
                    'document_number' => $documentNumber,
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'work_order_id' => $request->work_order_id,
                    'user_id' => $request->user_id,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal' => $request->subtotal,
                    'tax_amount' => $request->tax_amount,
                    'total' => $request->total,
                    'status' => $isDraft ? 'draft' : ($request->document_type === 'quote' ? 'pending' : 'completed'),
                    'payment_status' => $request->payment_status,
                    'is_credited' => $request->is_credited,
                    'payment_method' => $paymentMethod,
                    'observations' => $request->observations,
                ]);

                // B. Registrar cada producto/servicio del detalle
                foreach ($request->items as $item) {
                    $sale->details()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0.00,
                        'total' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0.00),
                    ]);

                    // Deducir stock solo si no es cotización y es producto físico
                    if ($request->document_type !== 'quote' && !$isDraft && isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product && $product->item_type == 1) {
                            $product->stock -= $item['quantity'];
                            $product->save();
                        }
                    }
                }

                // 💰 ESCALABILIDAD FINANCIERA: Actualizar cuentas según métodos de pago
                // Si $request->document_type !== 'quote' (Es Nota de Venta o Factura)
                if ($request->document_type !== 'quote' && !$isDraft) {
                    $request->merge(['payment_method' => $paymentMethod]);
                    $this->processFinancialRecord($sale, $request);
                }

                $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, $linkedWorkOrder);
                if (!empty($technicianIds)) {
                    WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
                }

                if ($linkedWorkOrder && !$isDraft) {
                    WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
                }

                return $sale;
            });


            // =================================================================🚀
            // 📬 ENVÍO AUTOMÁTICO DE EMAIL CON PDF ADJUNTO
            // =================================================================🚀
            if ($sale->document_type !== 'quote' && $sale->status !== 'draft') {
                try {
                    $sale->load(['client', 'vehicle', 'user', 'details.product', 'technicians', 'financeRecord.paymentDistributions.account']);

                    // 1. Mapear el ID de la marca al nombre (tal cual lo haces en tus otros métodos)
                    $vehicleBrands = config('vehicle_brands', []);
                    if ($sale->vehicle && isset($sale->vehicle->brand)) {
                        $brandId = $sale->vehicle->brand;
                        $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
                    }

                    // 2. Generamos el PDF en memoria usando tu misma vista de ventas
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('sales.pdf_sale', ['sale' => $sale, 'isEmail' => true]);
                    $pdfRawData = $pdf->output(); // Esto saca el PDF como una cadena binaria limpia
                    $pdfFileName = $sale->document_type . '_' . $sale->document_number . '.pdf';

                    // 3. Data para la plantilla HTML del correo
                    $data = [
                        'titulo_asunto' => ($sale->document_type === 'invoice' ? 'Factura' : 'Nota de Venta') . ' #' . $sale->document_number,
                        'cliente' => $sale->client->full_name ?? 'Cliente',
                        'mensaje_principal' => 'Tu transacción ha sido procesada con éxito. Agradecemos tu confianza en Luxury Evys. Adjunto a este correo encontrarás el comprobante oficial en formato PDF con el detalle de los servicios prestados.',
                        'vehiculo' => $sale->vehicle ? ($sale->vehicle->brand . ' ' . $sale->vehicle->model) : 'N/A',
                        'placa' => $sale->vehicle->license_plate ?? 'N/A',
                        'accion' => 'Comprobante de Servicio Generado'
                    ];

                    // 4. Enviamos pasando la data y el PDF generado
                    if (!empty($sale->client->email)) {
                        \Illuminate\Support\Facades\Mail::to($sale->client->email)->send(
                            new \App\Mail\System\TestNotificationMail($data, $pdfRawData, $pdfFileName)
                        );
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al enviar el correo electrónico.',
                        'error' => $e->getMessage()
                    ], 201);
                }
            }



            // 3. Respuesta exitosa al frontend con el registro completo cargando sus detalles
            return response()->json([
                'success' => true,
                'message' => 'El registro se procesó correctamente.',
                'data' => $sale->load(['details', 'technicians'])
            ], 201);
        } catch (Exception $e) {
            // Si algo truena dentro del bloque, el DB::transaction hace rollback automático
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process financial record for sale and update accounts
     */
    private function processFinancialRecord($sale, Request $request)
    {
        // 1. Buscar si ya existe un registro financiero para esta venta
        $financeRecord = FinanceRecord::where('invoice_number', $sale->document_number)->first();

        // 2. Si ya existe, revertir los saldos de las distribuciones anteriores
        if ($financeRecord) {
            foreach ($financeRecord->paymentDistributions as $distribution) {
                $account = Account::find($distribution->account_id);
                if ($account) {
                    $account->updateBalance($distribution->amount, FinanceRecord::TYPE_EXPENSE); // Restar
                }
            }
            // Eliminar las distribuciones y los movimientos financieros anteriores
            $financeRecord->paymentDistributions()->delete();

            if (method_exists($sale, 'financialMovement')) {
                $sale->financialMovement()->delete();
            }
        } else {
            $financeRecord = new FinanceRecord();
        }

        $entryDate = $sale->service_date instanceof \Carbon\Carbon
            ? $sale->service_date->format('Y-m-d')
            : (is_string($sale->service_date) ? substr($sale->service_date, 0, 10) : now()->format('Y-m-d'));

        $paymentMethod = $request->payment_method ?? $sale->payment_method ?? 'Efectivo';

        $totalPaid = 0;
        if ($request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0) {
            $totalPaid = collect($request->payment_distributions)->sum('amount');
        } else {
            if ($sale->payment_status === 'paid') {
                $totalPaid = $sale->total;
            } else {
                $totalPaid = $sale->financeRecord ? $sale->financeRecord->paymentDistributions->sum('amount') : $sale->total;
            }
        }

        // 3. Crear/Actualizar el registro financiero principal
        $financeRecord->fill([
            'entry_date' => $entryDate,
            'type' => FinanceRecord::TYPE_INCOME,
            'account_id' => 1, // Default
            'payment_method' => $paymentMethod,
            'amount' => $totalPaid,
            'work_order_number' => WorkOrderSaleSync::resolveFinanceWorkOrderNumber(
                $sale->work_order_id,
                $sale->document_number
            ),
            'invoice_number' => $sale->document_number,
            'description' => 'Venta: ' . $sale->document_type . ' - ' . $sale->document_number,
            'user_id' => $sale->user_id ?? auth()->id() ?? 1,
        ]);
        $financeRecord->save();

        // 4. Procesar pagos distribuidos si existen
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
                    'Venta: ' . $sale->document_type . ' - ' . $sale->document_number . ' - ' . $distribution['payment_method'],
                    $entryDate,
                    [
                        'document_type' => $sale->document_type,
                        'document_number' => $sale->document_number,
                        'payment_method' => $distribution['payment_method'],
                        'finance_record_id' => $financeRecord->id,
                    ]
                );
            }
        } else {
            // Si no hay pagos distribuidos, usar el método de pago único
            $accountId = 1; // Default: Caja chica (Efectivo)
            if (strtolower($paymentMethod) === 'transferencia' || strtolower($paymentMethod) === 'transfer') {
                $accountId = 2; // Banco Pichincha
            }

            // Crear distribución de pago única
            PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $accountId,
                'amount' => $sale->total,
                'payment_method' => $paymentMethod,
            ]);

            // Actualizar el saldo de la cuenta
            $account = Account::find($accountId);
            if ($account) {
                $account->updateBalance($sale->total, FinanceRecord::TYPE_INCOME);
            }

            // Registrar movimiento financiero en financial_movements
            $sale->registerMovement(
                $accountId,
                'income',
                $sale->total,
                'Venta: ' . $sale->document_type . ' - ' . $sale->document_number . ' - ' . $paymentMethod,
                $entryDate,
                [
                    'document_type' => $sale->document_type,
                    'document_number' => $sale->document_number,
                    'payment_method' => $paymentMethod,
                    'finance_record_id' => $financeRecord->id,
                ]
            );
        }

        // Si es crédito ('is_credited' => true), registramos solo el abono inicial si lo hay
        // La lógica de pagos parciales se maneja con payment_status = 'partial'
    }

    /**
     * Obtiene el método de pago real desde los pagos distribuidos (si existen).
     */
    private function resolveSalePaymentMethod(Request $request): string
    {
        if (
            $request->has('payment_distributions')
            && is_array($request->payment_distributions)
            && count($request->payment_distributions) > 0
        ) {
            $methods = collect($request->payment_distributions)
                ->pluck('payment_method')
                ->filter()
                ->unique()
                ->values();

            if ($methods->count() === 1) {
                return (string) $methods->first();
            }

            if ($methods->count() > 1) {
                return $methods->implode(', ');
            }
        }

        return (string) ($request->payment_method ?? 'Efectivo');
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
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('service_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('service_date', '<=', $request->end_date);
            }

            // Filtro por estado de pago
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            // Obtener todos los resultados sin paginación para el PDF
            $sales = $query->orderBy('service_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // Mapear el ID de la marca al nombre de la marca para el PDF
            $vehicleBrands = config('vehicle_brands', []);
            foreach ($sales as $sale) {
                if ($sale->vehicle && isset($sale->vehicle->brand)) {
                    $brandId = $sale->vehicle->brand;
                    $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
                }
            }

            // Generar PDF
            $pdf = Pdf::loadView('sales.pdf_list', compact('sales'));
            return $pdf->download('ventas_' . date('Y-m-d_H-i-s') . '.pdf');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateSinglePDF(Request $request, int $id)
    {
        try {
            $sale = Sale::with(['client', 'vehicle', 'user', 'details.product', 'technicians', 'financeRecord.paymentDistributions.account'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            // Mapear el ID de la marca al nombre de la marca para el PDF
            $vehicleBrands = config('vehicle_brands', []);
            if ($sale->vehicle && isset($sale->vehicle->brand)) {
                $brandId = $sale->vehicle->brand;
                $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
            }

            if ($request->has('print')) {
                return view('sales.pdf_sale', compact('sale'));
            }
            $pdf = Pdf::loadView('sales.pdf_sale', compact('sale'));
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
            $sale = Sale::with(['details.product', 'client', 'vehicle', 'technicians', 'financeRecord.paymentDistributions.account', 'workOrder'])->find((int) $id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $sale
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle de la venta.',
                'error' => $e->getMessage()
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
            'document_number' => 'nullable|string|unique:sales,document_number,' . $id,
            'client_id' => 'nullable|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'mileage' => 'nullable|integer',
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
            'payment_distributions.*.payment_method' => 'required|string',
            'is_draft' => 'nullable|boolean',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
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
            $wasDraft = $sale->status === 'draft';
            $isNowSale = $request->has('document_type') && $request->document_type !== 'quote';
            $isDraft = $request->boolean('is_draft');
            $isFinishingDraft = $wasDraft && !$isDraft;

            // Validar pagos distribuidos solo si no es cotización y no es borrador
            $docType = $request->has('document_type') ? $request->document_type : $sale->document_type;
            if ($docType !== 'quote' && !$isDraft) {
                $hasDistributions = $request->has('payment_distributions');
                $isCredited = $request->has('is_credited') ? $request->boolean('is_credited') : $sale->is_credited;

                // Recalcular el total esperado de los items
                $finalTotal = $sale->total;
                if ($request->has('items')) {
                    $subtotal = 0;
                    foreach ($request->items as $item) {
                        $subtotal += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                    }
                    $taxAmount = $docType === 'invoice' ? $subtotal * 0.15 : 0;
                    $finalTotal = $subtotal + $taxAmount;
                }

                if ($hasDistributions) {
                    $distributions = $request->payment_distributions;
                    if ((!is_array($distributions) || count($distributions) === 0) && !$isCredited) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    $totalDist = (is_array($distributions) && count($distributions) > 0) ? array_sum(array_column($distributions, 'amount')) : 0;
                    if ($totalDist <= 0 && !$isCredited) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    if ($totalDist > $finalTotal + 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La suma de los pagos no puede ser mayor al total.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    if (abs($totalDist - $finalTotal) <= 0.01) {
                        $paymentStatus = 'paid';
                    } elseif ($totalDist > 0) {
                        $paymentStatus = 'partial';
                    } else {
                        $paymentStatus = 'pending';
                    }
                    $request->merge(['payment_status' => $paymentStatus]);
                } else {
                    $totalDist = $sale->financeRecord ? $sale->financeRecord->paymentDistributions->sum('amount') : 0;
                    if ($totalDist <= 0 && !$isCredited) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    if ($totalDist > $finalTotal + 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La suma de los pagos no puede ser mayor al total.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    if (abs($totalDist - $finalTotal) <= 0.01) {
                        $paymentStatus = 'paid';
                    } elseif ($totalDist > 0) {
                        $paymentStatus = 'partial';
                    } else {
                        $paymentStatus = 'pending';
                    }
                    $request->merge(['payment_status' => $paymentStatus]);
                }
            }

            // Validar stock si se convierte a venta o si ya es venta
            if ($isNowSale || ($sale->document_type !== 'quote' && !$isDraft)) {
                if ($request->has('items')) {
                    foreach ($request->items as $item) {
                        if (isset($item['product_id']) && $item['product_id']) {
                            $product = ModelsProduct::find($item['product_id']);
                            if ($product && $product->item_type == 1) { // Solo si es Producto Físico
                                $quantityNeeded = $item['quantity'];
                                if (!$wasQuote && !$wasDraft && isset($item['id'])) {
                                    $oldDetail = $sale->details->firstWhere('id', $item['id']);
                                    if ($oldDetail && $oldDetail->product_id == $item['product_id']) {
                                        $quantityNeeded -= $oldDetail->quantity;
                                    }
                                }

                                if ($quantityNeeded > 0 && $product->stock < $quantityNeeded) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado adicional: {$quantityNeeded}",
                                        'error' => 'stock_insufficient'
                                    ], 400);
                                }
                            }
                        }
                    }
                }
            }

            // Validar descuentos y margen (sólo aplica para item_type == 1 / Productos Físicos)
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product && $product->item_type == 1) {
                            // A. Validar que el precio final no sea menor al precio de compra (purchase_price)
                            $itemDiscount = (float) ($item['discount'] ?? 0.00);
                            $finalPrice = ($item['quantity'] * $item['price']) - $itemDiscount;
                            $minFinalPrice = $item['quantity'] * ($product->purchase_price ?? 0.00);
                            if ($finalPrice < $minFinalPrice) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "El descuento excede el margen permitido para el producto: {$product->description}. El precio final no puede ser menor al costo de compra (\${$product->purchase_price} c/u).",
                                    'error' => 'price_below_cost'
                                ], 400);
                            }

                            // B. Validar porcentaje de descuento máximo definido
                            if ($product->discount_percentage > 0) {
                                $maxAllowedByPct = ($item['quantity'] * $item['price']) * ($product->discount_percentage / 100);
                                if ($itemDiscount > $maxAllowedByPct) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "El descuento excede el porcentaje máximo permitido ({$product->discount_percentage}%) para el producto: {$product->description}.",
                                        'error' => 'discount_exceeded'
                                    ], 400);
                                }
                            }

                            // C. Validar max_discount (monto absoluto o porcentaje según lógica del sistema)
                            if ($product->max_discount > 0) {
                                $maxAllowedByVal = ($item['quantity'] * $item['price']) * ($product->max_discount / 100);
                                if ($itemDiscount > $maxAllowedByVal) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "El descuento excede el máximo permitido para el producto: {$product->description}.",
                                        'error' => 'discount_exceeded'
                                    ], 400);
                                }
                            }
                        }
                    }
                }
            }

            $oldDocumentNumber = $sale->document_number;

            $status = $sale->status;
            if ($isFinishingDraft) {
                $status = $request->document_type === 'quote' ? 'pending' : 'completed';
            } else if ($isDraft) {
                $status = 'draft';
            }

            // Ejecutar la actualización completa dentro de una transacción para garantizar consistencia atómica
            DB::transaction(function () use ($sale, $request, $status, $oldDocumentNumber, $wasQuote, $isNowSale, $wasDraft, $isFinishingDraft) {
                // Actualizar campos operativos básicos
                $sale->update($request->only([
                    'document_number',
                    'client_id',
                    'vehicle_id',
                    'mileage',
                    'service_date',
                    'observations',
                    'payment_method',
                    'document_type',
                    'payment_status',
                    'is_credited'
                ]) + ['status' => $status]);

                if ($request->has('technicians')) {
                    $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, null);
                    WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
                }

                // Si el número de documento cambió, actualizar el registro financiero y movimientos asociados
                if ($request->has('document_number') && $request->document_number !== $oldDocumentNumber) {
                    $financeRecord = \App\Models\Finance\FinanceRecord::where('invoice_number', $oldDocumentNumber)->first();
                    if ($financeRecord) {
                        $financeRecord->update([
                            'work_order_number' => WorkOrderSaleSync::resolveFinanceWorkOrderNumber(
                                $sale->work_order_id,
                                $request->document_number
                            ),
                            'invoice_number' => $request->document_number,
                            'description' => 'Venta: ' . $sale->document_type . ' - ' . $request->document_number,
                        ]);
                    }

                    // También actualizar descripciones de movimientos financieros asociados si existieran
                    if (method_exists($sale, 'financialMovement')) {
                        $movements = \App\Models\Finance\FinancialMovement::where('movable_id', $sale->id)
                            ->where('movable_type', get_class($sale))
                            ->get();

                        foreach ($movements as $movement) {
                            $newDesc = str_replace($oldDocumentNumber, $request->document_number, $movement->description);

                            $metadata = $movement->metadata ?? [];
                            if (isset($metadata['document_number']) && $metadata['document_number'] === $oldDocumentNumber) {
                                $metadata['document_number'] = $request->document_number;
                            }

                            $movement->update([
                                'description' => $newDesc,
                                'metadata' => $metadata
                            ]);
                        }
                    }
                }

                // Si se proporcionan items, actualizar el detalle
                if ($request->has('items')) {
                    // Obtener IDs de los items enviados
                    $itemIds = array_filter(array_map('intval', array_column($request->items, 'id')));

                    // Si ya era una venta, restauramos el stock de los items que van a ser eliminados
                    if (!$wasQuote && !$wasDraft) {
                        $itemsToDelete = $sale->details->whereNotIn('id', $itemIds);
                        foreach ($itemsToDelete as $deletedItem) {
                            if ($deletedItem->product_id) {
                                $product = ModelsProduct::find($deletedItem->product_id);
                                if ($product && $product->item_type == 1) { // Solo si es Producto Físico
                                    $product->stock += $deletedItem->quantity;
                                    $product->save();
                                }
                            }
                        }
                    }

                    // Eliminar items que no están en la solicitud
                    if (!empty($itemIds)) {
                        $sale->details()->whereNotIn('id', $itemIds)->delete();
                    } else {
                        $sale->details()->delete();
                    }
                    //comentario
                    // Actualizar o crear items
                    foreach ($request->items as $item) {
                        $itemTotal = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                        if (isset($item['id'])) {
                            $detail = $sale->details->firstWhere('id', $item['id']);

                            if ($detail) {
                                // Gestionar stock de la diferencia si ya era una venta
                                if (!$wasQuote && !$wasDraft) {
                                    if ($detail->product_id == ($item['product_id'] ?? null)) {
                                        if ($detail->product_id) {
                                            $product = ModelsProduct::find($detail->product_id);
                                            if ($product && $product->item_type == 1) {
                                                $diff = $item['quantity'] - $detail->quantity;
                                                $product->stock -= $diff;
                                                $product->save();
                                            }
                                        }
                                    } else {
                                        // Cambió de producto en la misma línea
                                        if ($detail->product_id) {
                                            $oldProduct = ModelsProduct::find($detail->product_id);
                                            if ($oldProduct && $oldProduct->item_type == 1) {
                                                $oldProduct->stock += $detail->quantity;
                                                $oldProduct->save();
                                            }
                                        }
                                        if (isset($item['product_id']) && $item['product_id']) {
                                            $newProduct = ModelsProduct::find($item['product_id']);
                                            if ($newProduct && $newProduct->item_type == 1) {
                                                $newProduct->stock -= $item['quantity'];
                                                $newProduct->save();
                                            }
                                        }
                                    }
                                }

                                // Actualizar item existente
                                $detail->update([
                                    'product_id' => $item['product_id'] ?? null,
                                    'description' => $item['description'],
                                    'quantity' => $item['quantity'],
                                    'price' => $item['price'],
                                    'discount' => $item['discount'] ?? 0,
                                    'total' => $itemTotal,
                                ]);
                            }
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

                            // Descontar stock si ya era una venta y es un item nuevo
                            if (!$wasQuote && !$wasDraft && isset($item['product_id']) && $item['product_id']) {
                                $product = ModelsProduct::find($item['product_id']);
                                if ($product && $product->item_type == 1) {
                                    $product->stock -= $item['quantity'];
                                    $product->save();
                                }
                            }
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

                    // Actualizar registro financiero y distribuciones si ya existían (venta ya activa)
                    if (!$wasQuote && !$wasDraft && $sale->document_type !== 'quote' && $sale->status !== 'draft') {
                        $this->processFinancialRecord($sale, $request);
                    }

                    // Si se convierte de cotización a venta, procesar el stock y finanzas
                    if (($wasQuote && $isNowSale) || $isFinishingDraft) {
                        $sale->status = 'completed';
                        $sale->save();

                        // Restar stock de TODOS los productos físicos (pues es su primera vez como venta)
                        foreach ($request->items as $item) {
                            if (isset($item['product_id']) && $item['product_id']) {
                                $product = ModelsProduct::find($item['product_id']);
                                if ($product && $product->item_type == 1) {
                                    $product->stock -= $item['quantity'];
                                    $product->save();
                                }
                            }
                        }

                        // Procesar registro financiero
                        if ($request->document_type !== 'quote') {
                            $this->processFinancialRecord($sale, $request);

                            if ($sale->work_order_id) {
                                $linkedWorkOrder = \App\Models\WorkOrder\WorkOrder::find($sale->work_order_id);
                                if ($linkedWorkOrder) {
                                    WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
                                }
                            }
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'El registro fue actualizado correctamente.',
                'data' => $sale->load('details')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            DB::transaction(function () use ($sale) {
                // 1. Reversar movimientos de cuentas y registros financieros
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

                    // Eliminar las distribuciones y el registro financiero
                    $financeRecord->paymentDistributions()->delete();
                    $financeRecord->delete();
                }

                // 2. Eliminar movimientos financieros asociados a la venta
                if (method_exists($sale, 'financialMovement')) {
                    $sale->financialMovement()->delete();
                }

                // 3. Revertir el Stock de los productos (si era una venta completada)
                if ($sale->document_type !== 'quote' && $sale->status !== 'canceled') {
                    foreach ($sale->details as $detail) {
                        if ($detail->product_id) {
                            $product = ModelsProduct::find($detail->product_id);
                            if ($product) {
                                $product->stock += $detail->quantity;
                                $product->save();
                            }
                        }
                    }
                }

                // 4. Eliminar los detalles y finalmente la venta
                $sale->details()->delete();
                $sale->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'La venta ha sido eliminada correctamente de la base de datos.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispatch sale - Create sale with pending payment (warehouse output)
     */
    public function dispatchSale(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|unique:sales,document_number',
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'mileage' => 'nullable|integer',
            'service_date' => 'nullable|date',
            'subtotal' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'total' => 'required|numeric',
            'observations' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'items.*.product_id' => 'nullable|exists:products,id',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
        ]);

        try {
            $linkedWorkOrder = null;
            if ($request->work_order_id) {
                $linkedWorkOrder = WorkOrderSaleSync::assertReadyForInvoicing((int) $request->work_order_id);
                $request->merge(['document_number' => $linkedWorkOrder->number]);
            }

            // Validar stock antes de procesar el despacho
            foreach ($request->items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1 && $product->stock < $item['quantity']) {
                        return response()->json([
                            'success' => false,
                            'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                            'error' => 'stock_insufficient'
                        ], 400);
                    }
                }
            }

            // Validar descuentos máximos
            foreach ($request->items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1 && $product->max_discount !== null) {
                        $maxDiscountAmount = ($item['quantity'] * $item['price']) * ($product->max_discount / 100);
                        if ($item['discount'] > $maxDiscountAmount) {
                            return response()->json([
                                'success' => false,
                                'message' => "Descuento excede el máximo permitido para el producto: {$product->description}. Máximo: {$maxDiscountAmount}, Ingresado: {$item['discount']}",
                                'error' => 'discount_exceeded'
                            ], 400);
                        }
                    }
                }
            }

            $sale = DB::transaction(function () use ($request, $linkedWorkOrder) {
                // Crear la venta con pago pendiente
                $sale = Sale::create([
                    'document_type' => 'sale_note',
                    'document_number' => $request->document_number,
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'work_order_id' => $request->work_order_id,
                    'user_id' => $request->user_id,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal' => $request->subtotal,
                    'tax_amount' => $request->tax_amount,
                    'total' => $request->total,
                    'status' => 'completed',
                    'payment_status' => 'pending',
                    'is_credited' => true,
                    'payment_method' => 'credit',
                    'observations' => $request->observations,
                ]);

                // Registrar cada producto/servicio del detalle
                foreach ($request->items as $item) {
                    $sale->details()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0.00,
                        'total' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0.00),
                    ]);

                    // Deducir stock
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product) {
                            $product->stock -= $item['quantity'];
                            $product->save();
                        }
                    }
                }

                $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, $linkedWorkOrder);
                if (!empty($technicianIds)) {
                    WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
                }

                if ($linkedWorkOrder) {
                    WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
                }

                return $sale;
            });

            return response()->json([
                'success' => true,
                'message' => 'Venta despachada correctamente con pago pendiente.',
                'data' => $sale->load(['details', 'technicians'])
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al despachar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register payment for a pending sale
     */
    public function registerPayment(Request $request, int $id)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'convert_to_invoice' => 'nullable|boolean',
        ]);

        try {
            $sale = Sale::with(['details', 'financeRecord.paymentDistributions'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'La venta no existe.'
                ], 404);
            }

            if ($sale->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta ya está pagada.'
                ], 400);
            }

            if ($sale->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede registrar pago para ventas con estado pendiente.'
                ], 400);
            }

            DB::transaction(function () use ($sale, $request) {
                // Actualizar estado de pago y método
                $sale->update([
                    'payment_status' => 'paid',
                    'payment_method' => $request->payment_method,
                    'status' => 'completed',
                ]);

                // Convertir a factura si se solicita
                if ($request->convert_to_invoice && $sale->document_type === 'sale_note') {
                    $sale->update(['document_type' => 'invoice']);
                }

                // Procesar registro financiero
                $this->processFinancialRecord($sale, $request);
            });

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado correctamente.',
                'data' => $sale->load('details')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a single sale detail.
     */
    public function destroyDetail(int $id)
    {
        try {
            $detail = \App\Models\Sales\SaleDetail::find($id);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ítem de venta no existe.'
                ], 404);
            }

            $sale = $detail->sale;
            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta asociada no encontrada.'
                ], 404);
            }

            // Regla de seguridad: Si la venta ya está anulada, no debería editarse
            if ($sale->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar una venta que ya ha sido anulada.'
                ], 400);
            }

            DB::transaction(function () use ($detail, $sale) {
                // Si la venta no es cotización ni borrador, devolvemos el stock del producto físico
                if ($sale->document_type !== 'quote' && $sale->status !== 'draft') {
                    if ($detail->product_id) {
                        $product = ModelsProduct::find($detail->product_id);
                        if ($product && $product->item_type == 1) { // Solo si es Producto Físico
                            $product->stock += $detail->quantity;
                            $product->save();
                        }
                    }
                }

                // Eliminar el detalle
                $detail->delete();

                // Recalcular los totales de la venta
                $subtotal = $sale->details()->sum('total');
                $taxAmount = $sale->document_type === 'invoice' ? $subtotal * 0.15 : 0;
                $total = $subtotal + $taxAmount;

                $sale->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'El ítem fue eliminado de la base de datos correctamente.',
                'sale' => $sale->fresh()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el ítem de la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print PDF directly to the configured Windows printer.
     */
    public function printDirect(int $id)
    {
        try {
            $sale = Sale::with(['client', 'vehicle', 'user', 'details.product', 'technicians', 'financeRecord.paymentDistributions.account'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            // Mapear el ID de la marca al nombre de la marca para el PDF
            $vehicleBrands = config('vehicle_brands', []);
            if ($sale->vehicle && isset($sale->vehicle->brand)) {
                $brandId = $sale->vehicle->brand;
                $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
            }

            // 1. Generar el PDF y guardarlo en un archivo temporal
            $pdf = Pdf::loadView('sales.pdf_sale', compact('sale'));
            $tempFileName = 'temp_sale_' . $sale->id . '_' . time() . '.pdf';
            $tempPath = storage_path('app/' . $tempFileName);
            $pdf->save($tempPath);

            // 2. Obtener configuración
            $printerName = env('PRINTER_NAME', 'L5290 Series(Network)');
            $edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

            // 3. Ejecutar comando de impresión en Windows usando msedge
            if (file_exists($edgePath)) {
                $command = sprintf(
                    'start /B "" "%s" --headless --print-to-printer="%s" "%s"',
                    $edgePath,
                    $printerName,
                    $tempPath
                );
                pclose(popen($command, 'r'));
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró Microsoft Edge en el servidor para realizar la impresión directa.'
                ], 500);
            }

            // 4. Borrar el archivo temporal después de 15 segundos en segundo plano
            dispatch(function () use ($tempPath) {
                sleep(15);
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            })->afterResponse();

            return response()->json([
                'success' => true,
                'message' => 'Impresión directa enviada a: ' . $printerName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la impresión directa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispatch mail for quotes manually via button.
     */
    public function enviarCotizacionPorCorreo(int $id)
    {
        try {
            // Buscamos el registro verificando relaciones clave
            $sale = Sale::with(['client', 'vehicle'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de cotización no existe.'
                ], 404);
            }

            // Validación de seguridad por si intentan dispararlo en un documento que no corresponde
            /* if ($sale->document_type !== 'quote') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este documento no es una cotización informativa.'
                ], 400);
            } */

            if (empty($sale->client->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente asignado no tiene una dirección de correo electrónico registrada.'
                ], 400);
            }

            $isQuote = $sale->document_type === 'quote';

            // Preparamos los datos dinámicos utilizando las columnas exactas de tus modelos
            $data = [
                'titulo_asunto' => $isQuote ? 'Presupuesto / Cotización #' . $sale->document_number : 'Comprobante de Venta #' . $sale->document_number,
                'cliente' => $sale->client->full_name ?? 'Cliente',
                'mensaje_principal' => $isQuote 
                    ? 'Adjuntamos la cotización y el presupuesto solicitado para los mantenimientos, servicios o repuestos de tu vehículo. Recuerda que este documento es de carácter informativo.'
                    : 'Adjuntamos el comprobante detallado de tu compra por los servicios o repuestos adquiridos. ¡Gracias por confiar en nosotros!',
                'vehiculo' => $sale->vehicle ? ($sale->vehicle->brand . ' ' . $sale->vehicle->model) : 'N/A',
                'placa' => $sale->vehicle->license_plate ?? 'N/A',
                'accion' => $isQuote ? 'Cotización de Servicios' : 'Comprobante de Venta'
            ];

            // Generamos el PDF
            $pdf = Pdf::loadView('sales.pdf_sale', ['sale' => $sale, 'isEmail' => true]);
            $pdfRawData = $pdf->output();
            $pdfFileName = ($isQuote ? 'cotizacion_' : 'comprobante_venta_') . $sale->document_number . '.pdf';

            Mail::to($sale->client->email)->send(
                new \App\Mail\System\TestNotificationMail($data, $pdfRawData, $pdfFileName)
            );

            return response()->json([
                'success' => true,
                'message' => $isQuote ? '¡Cotización enviada al correo del cliente con éxito!' : '¡Comprobante de venta enviado al correo del cliente con éxito!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al despachar el correo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

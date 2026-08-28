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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Jobs\ProcessElectronicInvoice;
use App\Services\SRI\ElectronicInvoiceService;
use App\Helpers\PdfHelper;

class SaleController extends Controller
{
    /**
     * Get next sequence number
     */
    public function getNextNumber(Request $request)
    {
        $docType = $request->query('document_type', 'sale_note');
        
        if ($docType === 'quote') {
            $number = SequenceService::previewNextQuoteNumber();
        } elseif ($docType === 'invoice') {
            $number = SequenceService::previewNumber('invoice');
        } elseif ($docType === 'work_order') {
            $number = SequenceService::previewNumber('work_order');
        } else {
            $number = SequenceService::previewNumber('sale_note');
        }

        return response()->json([
            'success' => true,
            'data' => $number
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

            // 1. Filtro por búsqueda (nombre, cédula del cliente, placa de vehículo, número de documento u orden de trabajo)
            if ($request->has('search') && $request->search != '') {
                $searchTerm = trim($request->search);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('document_number', 'like', "%{$searchTerm}%")
                        ->orWhere('work_order_number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('workOrder', function ($woQuery) use ($searchTerm) {
                            $woQuery->where('number', 'like', "%{$searchTerm}%");
                        })
                        ->orWhereHas('client', function ($clientQuery) use ($searchTerm) {
                            $clientQuery->where('full_name', 'like', "%{$searchTerm}%")
                                ->orWhere('n_document', 'like', "%{$searchTerm}%");
                        })
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($searchTerm) {
                            $vehicleQuery->where('license_plate', 'like', "%{$searchTerm}%");
                        });
                });
            }

            // 1.5 Filtro específico por Orden de Trabajo
            if ($request->has('work_order') && $request->work_order != '') {
                $woTerm = trim($request->work_order);
                $query->where(function ($q) use ($woTerm) {
                    $q->where('work_order_number', 'like', "%{$woTerm}%")
                        ->orWhereHas('workOrder', function ($woQuery) use ($woTerm) {
                            $woQuery->where('number', 'like', "%{$woTerm}%");
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

            // 3.5 Filtro por vehículo específico
            if ($request->has('vehicle_id') && $request->vehicle_id != '') {
                $query->where('vehicle_id', $request->vehicle_id);
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

            // 6. Excluir cotizaciones del listado de ventas (para la página de ventas/facturas)
            if ($request->boolean('exclude_quotes')) {
                $query->where('document_type', '!=', 'quote');
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
            'document_number' => 'nullable|string',
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
            'payment_method' => 'nullable|string',
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
            }

            $isDraft = $request->boolean('is_draft');

            // Validar pagos distribuidos solo si no es cotización y no es borrador
            if ($request->document_type !== 'quote' && !$isDraft) {
                if ($request->payment_status === 'pending' || $request->boolean('is_credited')) {
                    $request->merge([
                        'payment_status' => 'pending',
                        'is_credited' => true,
                        'payment_method' => $request->payment_method ?: 'Crédito / Pendiente',
                    ]);
                } else {
                    $hasDistributions = $request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0;

                    if (!$hasDistributions) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ], 400);
                    }

                    $totalDist = array_sum(array_column($request->payment_distributions, 'amount'));

                    if ($totalDist <= 0) {
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
                                    'message' => "El descuento total excede el porcentaje máximo permitido ({$product->discount_percentage}%) para el producto: {$product->description}.",
                                    'error' => 'discount_exceeded'
                                ], 400);
                            }
                        }

                        // C. Validar max_discount (monto absoluto o porcentaje según lógica del sistema)
                        if ($product->max_discount > 0) {
                            $maxAllowedByVal = $item['quantity'] * $product->max_discount;
                            if ($itemDiscount > $maxAllowedByVal) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "El descuento total excede el máximo permitido para el producto: {$product->description}.",
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

                // Consumir el número de documento de forma segura según el tipo de documento
                if ($request->document_type === 'quote') {
                    // Cotizaciones usan su propia secuencia independiente
                    $documentNumber = \App\Services\SequenceService::consumeQuoteNumber($request->document_number);
                } else {
                    // Si viene de una OT, igualmente le asignamos un secuencial de venta NV o FAC
                    $documentNumber = \App\Services\SequenceService::consumeNumber($request->document_type, $request->document_number);
                }

                // A. Crear la cabecera de la venta
                $sale = Sale::create([
                    'document_type' => $request->document_type,
                    'document_number' => $documentNumber,
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'work_order_id' => $request->work_order_id,
                    'work_order_number' => $linkedWorkOrder ? $linkedWorkOrder->number : ($request->work_order_number ?? ($request->work_order_id ? \App\Models\WorkOrder\WorkOrder::find($request->work_order_id)?->number : null)),
                    'user_id' => auth()->id() ?? $request->user_id ?? 1,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal' => $request->subtotal,
                    'tax_amount' => $request->tax_amount,
                    'total' => $request->total,
                    'status' => $isDraft ? 'draft' : ($request->document_type === 'quote' || $request->payment_status === 'pending' || $request->payment_status === 'partial' ? 'pending' : 'completed'),
                    'payment_status' => $request->payment_status,
                    'is_credited' => $request->is_credited,
                    'payment_method' => $paymentMethod,
                    'observations' => $request->observations,
                ]);

                // B. Registrar cada producto/servicio del detalle
                foreach ($request->items as $item) {
                    $qty      = (float)($item['quantity'] ?? 1);
                    $price    = (float)($item['price'] ?? 0);
                    $discount = (float)($item['discount'] ?? 0);
                    $taxRate  = (float)($item['tax_rate'] ?? 15.00);
                    $base     = ($qty * $price) - $discount;
                    $taxValue = round($base * ($taxRate / 100), 2);

                    $sale->details()->create([
                        'product_id'  => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity'    => $qty,
                        'price'       => $price,
                        'discount'    => $discount,
                        'tax_rate'    => $taxRate,
                        'tax_value'   => $taxValue,
                        'total'       => $base,
                    ]);

                    // Deducir stock de forma atómica solo si no es cotización y es producto físico
                    if ($request->document_type !== 'quote' && !$isDraft && isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product && $product->item_type == 1) {
                            ModelsProduct::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
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

                // Marcar la factura electrónica como CREADA (antes de que el job la procese)
                if ($request->document_type === 'invoice' && !$isDraft) {
                    $sale->update(['sri_status' => 'CREADA']);
                }

                if ($request->has('quote_id') && $request->quote_id) {
                    \App\Models\Sales\Quote::where('id', $request->quote_id)->update([
                        'converted_sale_id' => $sale->id,
                        'status' => 'completed',
                    ]);
                }

                return $sale;
            });

            // ── Despachar job de Facturación Electrónica SRI ─────────────
            if ($sale->document_type === 'invoice' && $sale->status !== 'draft') {
                try {
                    ProcessElectronicInvoice::dispatch($sale->id);
                    Log::info("[SRI] Job despachado para venta #{$sale->id}");
                } catch (\Throwable $e) {
                    Log::error("[SRI] Error al despachar factura electrónica para venta #{$sale->id}: " . $e->getMessage());
                }
            }


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
                        'accion' => 'Comprobante de Servicio Generado',
                        'encuesta_url' => 'https://docs.google.com/forms/d/1pcVsHD2XcGbghjW4j7XgDVihb5-oB7otvnvMbd4sBY0/viewform'
                    ];

                    // 4. Enviamos pasando la data y el PDF generado
                    if (!empty($sale->client->email)) {
                        \Illuminate\Support\Facades\Mail::to($sale->client->email)->send(
                            new \App\Mail\System\TestNotificationMail($data, $pdfRawData, $pdfFileName)
                        );
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Error al enviar email automático de venta:', ['error' => $e->getMessage()]);
                }
            }



            $this->syncReplacementReminders($sale);

            // Guardar comprobantes adjuntos si fueron enviados en el request
            if ($request->hasFile('receipts')) {
                try {
                    $storageService = app(\App\Services\InvoiceStorageService::class);
                    $clientName = $sale->client->full_name ?? ($sale->client ? trim(($sale->client->name ?? '') . ' ' . ($sale->client->surname ?? '')) : 'Cliente');
                    $storageService->attachReceiptsToModel(
                        $sale,
                        $sale->document_number ?: $sale->id,
                        $clientName,
                        $request->file('receipts'),
                        $sale->service_date ?? now()
                    );
                } catch (\Exception $e) {
                    \Log::warning('Error al adjuntar comprobantes a Sale:', ['error' => $e->getMessage()]);
                }
            }

            // 3. Respuesta exitosa al frontend con el registro completo cargando sus detalles
            return response()->json([
                'success' => true,
                'message' => 'El registro se procesó correctamente.',
                'data' => $sale->load(['details', 'technicians', 'attachments'])
            ], 201);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al procesar venta: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Si algo truena dentro del bloque, el DB::transaction hace rollback automático
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica si la venta es una factura emitida en ambiente de pruebas (SRI ambiente 1).
     * En ambiente de producción (ambiente 2) retorna false para que se realicen las operaciones en las cuentas respectivas.
     * En ambiente de pruebas (ambiente 1) retorna true y no se realizan cambios en las cuentas.
     */
    private function isTestEnvironmentInvoice(Sale $sale): bool
    {
        if ($sale->document_type !== 'invoice') {
            return false;
        }

        // Obtener la sucursal emisora asociada a la venta
        $sucursalId = $sale->client->sucursale_id ?? optional($sale->user)->sucursale_id ?? 1;
        $sucursal = \App\Models\Config\Sucursale::find($sucursalId) ?? \App\Models\Config\Sucursale::first();

        if ($sucursal && !empty($sucursal->ambiente)) {
            return (int) $sucursal->ambiente === 1; // 1: Pruebas, 2: Producción
        }

        return (int) env('SRI_AMBIENTE', 1) === 1;
    }

    /**
     * Process financial record for sale and update accounts
     */
    private function processFinancialRecord($sale, Request $request)
    {
        // En ambiente de pruebas para facturas (SRI_AMBIENTE = 1), no alterar cuentas financieras ni saldos
        if ($this->isTestEnvironmentInvoice($sale)) {
            Log::info("[SRI Pruebas] Factura #{$sale->document_number} procesada en modo pruebas: se omiten movimientos de cuentas y saldos.");
            return;
        }

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
        if ($sale->payment_status === 'pending') {
            $totalPaid = 0;
        } elseif ($request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0) {
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

        // 4. Procesar pagos distribuidos si existen (solo si no es pendiente)
        if ($sale->payment_status !== 'pending') {
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

            if ($sale->document_type === 'invoice') {
                $sucursal = \App\Models\Config\Sucursale::find($sale->client->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
                $autorizacion = [
                    'numeroAutorizacion' => $sale->sri_access_key,
                    'fechaAutorizacion'  => $sale->sri_authorization_date ? $sale->sri_authorization_date->format('d/m/Y H:i:s') : null,
                    'estado'             => $sale->sri_status,
                ];

                if ($request->has('print')) {
                    return view('pdf.ride', compact('sale', 'sucursal', 'autorizacion'));
                }
                $pdf = Pdf::loadView('pdf.ride', compact('sale', 'sucursal', 'autorizacion'));
                $fileName = 'RIDE_' . $sale->document_number . '.pdf';
                return $pdf->stream($fileName);
            }

            if ($request->has('print')) {
                return view('sales.pdf_sale', compact('sale'));
            }
            $pdf = Pdf::loadView('sales.pdf_sale', compact('sale'));
            $fileName = PdfHelper::formatFileName($sale->document_type, $sale->document_number, $sale->client, $sale->vehicle);
            return $pdf->stream($fileName);
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
    public function show(int $id)
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

            // Regla: Si la cotización ya fue convertida, está bloqueada
            if ($sale->document_type === 'quote' && $sale->is_converted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida en venta/factura y no puede modificarse.'
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
                    if ($request->payment_status === 'pending') {
                        $totalDist = 0;
                    } else {
                        $totalDist = $sale->financeRecord ? $sale->financeRecord->paymentDistributions->sum('amount') : 0;
                    }
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
                                        'message' => "El descuento total excede el porcentaje máximo permitido ({$product->discount_percentage}%) para el producto: {$product->description}.",
                                        'error' => 'discount_exceeded'
                                    ], 400);
                                }
                            }

                            // C. Validar max_discount (monto absoluto o porcentaje según lógica del sistema)
                            if ($product->max_discount > 0) {
                                $maxAllowedByVal = $item['quantity'] * $product->max_discount;
                                if ($itemDiscount > $maxAllowedByVal) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "El descuento total excede el máximo permitido para el producto: {$product->description}.",
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
                $status = ($request->document_type === 'quote' || $request->payment_status === 'pending' || $request->payment_status === 'partial') ? 'pending' : 'completed';
            } else if ($isDraft) {
                $status = 'draft';
            } else if ($sale->status !== 'canceled') {
                if ($request->has('payment_status')) {
                    if ($request->payment_status === 'paid') {
                        $status = 'completed';
                    } elseif ($request->payment_status === 'pending' || $request->payment_status === 'partial') {
                        $status = 'pending';
                    }
                }
            }

            // Ejecutar la actualización completa dentro de una transacción para garantizar consistencia atómica
            DB::transaction(function () use ($sale, $request, $status, $oldDocumentNumber, $wasQuote, $isNowSale, $wasDraft, $isFinishingDraft) {
                // Actualizar campos operativos básicos
                $updateData = $request->only([
                    'document_number',
                    'client_id',
                    'vehicle_id',
                    'mileage',
                    'service_date',
                    'observations',
                    'payment_method',
                    'document_type',
                    'payment_status',
                    'is_credited',
                    'work_order_id'
                ]);
                if ($request->has('work_order_id')) {
                    $wo = \App\Models\WorkOrder\WorkOrder::find($request->work_order_id);
                    $updateData['work_order_number'] = $wo ? $wo->number : null;
                }
                $sale->update($updateData + ['status' => $status]);

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

            $this->syncReplacementReminders($sale);

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

                \App\Services\SequenceService::decrementNumberIfMatches($sale->document_type, $sale->document_number);
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
            'document_number' => 'nullable|string',
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
                $documentNumber = \App\Services\SequenceService::consumeNumber('sale_note', $request->document_number);
                
                // Crear la venta con pago pendiente
                $sale = Sale::create([
                    'document_type' => 'sale_note',
                    'document_number' => $documentNumber,
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'work_order_id' => $request->work_order_id,
                    'work_order_number' => $linkedWorkOrder ? $linkedWorkOrder->number : ($request->work_order_number ?? ($request->work_order_id ? \App\Models\WorkOrder\WorkOrder::find($request->work_order_id)?->number : null)),
                    'user_id' => $request->user_id,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal' => $request->subtotal,
                    'tax_amount' => $request->tax_amount,
                    'total' => $request->total,
                    'status' => 'pending',
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

            if (!$isQuote) {
                $data['encuesta_url'] = 'https://docs.google.com/forms/d/1pcVsHD2XcGbghjW4j7XgDVihb5-oB7otvnvMbd4sBY0/viewform';
            }

            // Generamos el PDF
            $pdf = Pdf::loadView('sales.pdf_sale', ['sale' => $sale, 'isEmail' => true]);
            $pdfRawData = $pdf->output();
            $pdfFileName = PdfHelper::formatFileName($sale->document_type, $sale->document_number, $sale->client, $sale->vehicle);

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

    // =========================================================================
    // ║               MÉTODOS DE FACTURACIÓN ELECTRÓNICA SRI                  ║
    // =========================================================================

    /**
     * Reenvía al SRI una factura que fue DEVUELTA o RECHAZADA.
     */
    public function reenviarSri(int $id)
    {
        try {
            $sale = Sale::findOrFail($id);

            if ($sale->document_type !== 'invoice') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo las facturas pueden enviarse al SRI.',
                ], 422);
            }

            if ($sale->sri_status === 'AUTORIZADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura ya está autorizada por el SRI.',
                ], 422);
            }

            ProcessElectronicInvoice::dispatch($sale->id);
            $sale->update(['sri_status' => 'CREADA', 'sri_error' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Factura encolada para reenvío al SRI.',
                'data'    => $sale->only(['id', 'document_number', 'sri_status']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar al SRI.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta en tiempo real el estado SRI de una factura electrónica.
     */
    public function estadoSri(int $id)
    {
        try {
            $sale = Sale::select([
                'id',
                'document_number',
                'document_type',
                'sri_access_key',
                'sri_status',
                'sri_authorization_date',
                'sri_error',
                'xml_path',
                'pdf_path',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $sale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el estado SRI.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga el XML firmado de la factura electrónica.
     */
    public function descargarXml(int $id)
    {
        try {
            $sale = Sale::findOrFail($id);

            if (!$sale->xml_path || !Storage::exists($sale->xml_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El XML de esta factura aún no está disponible.',
                ], 404);
            }

            $filename = 'factura_' . $sale->document_number . '.xml';

            return response(Storage::get($sale->xml_path), 200, [
                'Content-Type'        => 'application/xml',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el XML.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga el RIDE (PDF de representación impresa) de la factura electrónica.
     */
    public function descargarRide(int $id)
    {
        try {
            $sale = Sale::with(['details', 'client', 'vehicle', 'workOrder'])->findOrFail($id);

            $sucursal = \App\Models\Config\Sucursale::find($sale->client->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
            $autorizacion = [
                'numeroAutorizacion' => $sale->sri_access_key,
                'fechaAutorizacion'  => $sale->sri_authorization_date ? $sale->sri_authorization_date->format('d/m/Y H:i:s') : null,
                'estado'             => $sale->sri_status,
            ];

            $ridePath = app(\App\Services\SRI\ElectronicInvoiceService::class)->generarRide($sale, $sucursal, $autorizacion);
            $sale->update(['pdf_path' => $ridePath]);

            $filename = 'RIDE_' . $sale->document_number . '.pdf';

            return response(Storage::get($ridePath), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el RIDE.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envía o reenvía la factura electrónica por correo al cliente.
     */
    public function enviarEmail(Request $request, int $id)
    {
        try {
            $sale = Sale::with(['details', 'client', 'vehicle', 'workOrder'])->findOrFail($id);

            if ($sale->document_type !== 'invoice') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden enviar por correo facturas electrónicas.',
                ], 400);
            }

            $emailDestino = $request->input('email', $sale->client->email ?? null);
            if (empty($emailDestino)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no tiene un correo electrónico configurado.',
                ], 422);
            }

            // Asegurar que RIDE y XML existan
            if (!$sale->pdf_path || !Storage::exists($sale->pdf_path)) {
                app(\App\Services\SRI\ElectronicInvoiceService::class)->procesar($sale);
                $sale->refresh();
            }

            $sent = app(\App\Services\SRI\ElectronicInvoiceService::class)->enviarPorCorreo($sale, $emailDestino);

            if ($sent) {
                return response()->json([
                    'success' => true,
                    'message' => "Factura enviada exitosamente a {$emailDestino}",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo enviar el correo. Verifique la configuración SMTP.',
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir una cotización en venta (sale_note) o factura (invoice).
     * Crea un nuevo documento y bloquea la cotización original.
     */
    public function convertQuote(Request $request, int $quoteId)
    {
        $request->validate([
            'document_type' => 'required|in:sale_note,invoice',
            'payment_method' => 'required|string',
            'payment_status' => 'required|in:paid,partial,pending',
            'is_credited' => 'nullable|boolean',
            'payment_distributions' => 'nullable|array',
            'payment_distributions.*.account_id' => 'required|exists:accounts,id',
            'payment_distributions.*.amount' => 'required|numeric|min:0',
            'payment_distributions.*.payment_method' => 'required|string',
        ]);

        try {
            $quote = Sale::with(['details', 'technicians'])->findOrFail($quoteId);

            // Validar que sea una cotización
            if ($quote->document_type !== 'quote') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden convertir cotizaciones.'
                ], 400);
            }

            // Validar que no esté ya convertida
            if ($quote->is_converted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida anteriormente.',
                    'converted_sale_id' => $quote->converted_to_sale_id
                ], 400);
            }

            // Validar que no esté anulada
            if ($quote->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede convertir una cotización anulada.'
                ], 400);
            }

            $newSale = null;

            DB::transaction(function () use ($quote, $request, &$newSale) {
                $newDocType = $request->document_type;

                // Generar nuevo número secuencial
                $newDocNumber = SequenceService::consumeNumber($newDocType);

                // Recalcular totales (los precios de cotización ya incluyen el IVA, por lo que el total es la suma directa)
                $total = 0;
                foreach ($quote->details as $detail) {
                    $total += ($detail->quantity * $detail->price) - ($detail->discount ?? 0);
                }

                if ($newDocType === 'invoice') {
                    $subtotal = round($total / 1.15, 2);
                    $taxAmount = round($total - $subtotal, 2);
                } else {
                    $subtotal = $total;
                    $taxAmount = 0;
                }

                // Crear la nueva venta/factura
                $newSale = Sale::create([
                    'document_type' => $newDocType,
                    'document_number' => $newDocNumber,
                    'client_id' => $quote->client_id,
                    'vehicle_id' => $quote->vehicle_id,
                    'work_order_id' => $quote->work_order_id,
                    'work_order_number' => $quote->workOrder ? $quote->workOrder->number : null,
                    'mileage' => $quote->mileage,
                    'service_date' => now()->toDateString(),
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'status' => 'completed',
                    'payment_status' => $request->payment_status,
                    'is_credited' => $request->boolean('is_credited'),
                    'payment_method' => $request->payment_method,
                    'observations' => $quote->observations,
                    'user_id' => $quote->user_id,
                ]);

                // Copiar los detalles de la cotización
                foreach ($quote->details as $detail) {
                    $newSale->details()->create([
                        'product_id' => $detail->product_id,
                        'description' => $detail->description,
                        'quantity' => $detail->quantity,
                        'price' => $detail->price,
                        'discount' => $detail->discount ?? 0,
                        'total' => ($detail->quantity * $detail->price) - ($detail->discount ?? 0),
                    ]);
                }

                // Copiar técnicos si existen
                if ($quote->technicians->isNotEmpty()) {
                    $newSale->technicians()->sync($quote->technicians->pluck('id'));
                }

                // Crear registro financiero si no es crédito pendiente ni factura de pruebas
                if ($request->payment_status !== 'pending' && !$this->isTestEnvironmentInvoice($newSale)) {
                    $financeRecord = FinanceRecord::create([
                        'type' => FinanceRecord::TYPE_INCOME,
                        'amount' => $total,
                        'description' => 'Venta: ' . $newDocType . ' - ' . $newDocNumber,
                        'invoice_number' => $newDocNumber,
                        'user_id' => $quote->user_id,
                        'entry_date' => now()->toDateString(),
                    ]);

                    // Procesar pagos distribuidos
                    if ($request->has('payment_distributions') && is_array($request->payment_distributions)) {
                        foreach ($request->payment_distributions as $distribution) {
                            PaymentDistribution::create([
                                'finance_record_id' => $financeRecord->id,
                                'account_id' => $distribution['account_id'],
                                'amount' => $distribution['amount'],
                                'payment_method' => $distribution['payment_method'],
                            ]);

                            $account = Account::find($distribution['account_id']);
                            if ($account) {
                                $account->updateBalance($distribution['amount'], FinanceRecord::TYPE_INCOME);
                            }

                            $newSale->registerMovement(
                                $distribution['account_id'],
                                'income',
                                $distribution['amount'],
                                'Venta: ' . $newDocType . ' - ' . $newDocNumber . ' - ' . $distribution['payment_method'],
                                now()->toDateString(),
                                [
                                    'document_type' => $newDocType,
                                    'document_number' => $newDocNumber,
                                    'payment_method' => $distribution['payment_method'],
                                    'finance_record_id' => $financeRecord->id,
                                ]
                            );
                        }
                    } else {
                        // Pago único
                        $accountId = 1; // Caja chica (Efectivo)
                        if (strtolower($request->payment_method) === 'transferencia' || strtolower($request->payment_method) === 'transfer') {
                            $accountId = 2;
                        }

                        PaymentDistribution::create([
                            'finance_record_id' => $financeRecord->id,
                            'account_id' => $accountId,
                            'amount' => $total,
                            'payment_method' => $request->payment_method,
                        ]);

                        $account = Account::find($accountId);
                        if ($account) {
                            $account->updateBalance($total, FinanceRecord::TYPE_INCOME);
                        }

                        $newSale->registerMovement(
                            $accountId,
                            'income',
                            $total,
                            'Venta: ' . $newDocType . ' - ' . $newDocNumber . ' - ' . $request->payment_method,
                            now()->toDateString(),
                            [
                                'document_type' => $newDocType,
                                'document_number' => $newDocNumber,
                                'payment_method' => $request->payment_method,
                                'finance_record_id' => $financeRecord->id,
                            ]
                        );
                    }
                }

                // Descontar stock de los productos
                foreach ($newSale->details as $detail) {
                    if ($detail->product_id) {
                        $product = ModelsProduct::find($detail->product_id);
                        if ($product && $product->stock !== null) {
                            $product->decrement('stock', $detail->quantity);
                        }
                    }
                }

                // Bloquear la cotización original
                $quote->update(['converted_to_sale_id' => $newSale->id]);

                // Procesar factura electrónica si es invoice
                if ($newDocType === 'invoice') {
                    ProcessElectronicInvoice::dispatch($newSale->id)->afterCommit();
                }
            });

            $this->syncReplacementReminders($newSale);

            return response()->json([
                'success' => true,
                'message' => 'Cotización convertida exitosamente a ' . ($request->document_type === 'invoice' ? 'factura' : 'nota de venta') . '.',
                'data' => $newSale->load(['details', 'client', 'vehicle'])
            ], 201);

        } catch (Exception $e) {
            Log::error('Error al convertir cotización: ' . $e->getMessage(), [
                'quote_id' => $quoteId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al convertir la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el historial de repuestos vendidos (amortiguadores, pastillas, aceites, filtros, AC).
     */
    public function getRepuestosHistorial()
    {
        try {
            // Palabras clave a filtrar
            $keywords = ['amortiguador', 'pastilla', 'freno', 'aceite', 'filtro', 'aire', 'acondicionado'];

            $query = \App\Models\Sales\SaleDetail::query()
                ->with(['sale.client', 'sale.vehicle', 'product.categorie'])
                ->whereHas('sale', function ($q) {
                    $q->where('status', '!=', 'canceled')
                      ->where('document_type', '!=', 'quote');
                })
                ->whereHas('product', function ($q) {
                    $q->where('item_type', 1); // Solo productos físicos (excluir servicios)
                })
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('description', 'like', "%{$kw}%");
                    }
                });

            $details = $query->orderBy('id', 'desc')->get();

            $vehicleBrands = config('vehicle_brands', []);

            $historial = $details->map(function ($detail) use ($vehicleBrands) {
                $sale = $detail->sale;
                $client = $sale ? $sale->client : null;
                $vehicle = $sale ? $sale->vehicle : null;
                
                $category = ($detail->product && $detail->product->categorie) 
                    ? $detail->product->categorie->title 
                    : 'Otros Repuestos';
                $nextSuggestion = 'Según manual';

                // Formatear marca
                $brandName = '';
                if ($vehicle && isset($vehicle->brand)) {
                    $brandId = $vehicle->brand;
                    $brandName = $vehicleBrands[$brandId] ?? $brandId;
                }

                return [
                    'id' => $detail->id,
                    'sale_id' => $detail->sale_id,
                    'fecha' => $sale ? $sale->service_date : null,
                    'comprobante' => $sale ? $sale->document_number : 'N/A',
                    'cliente' => $client ? $client->full_name : 'Consumidor Final',
                    'cliente_dni' => $client ? $client->n_document : 'N/A',
                    'vehiculo_placa' => $vehicle ? $vehicle->license_plate : 'N/A',
                    'vehiculo_modelo' => $vehicle ? trim(($brandName . ' ' . $vehicle->model)) : 'N/A',
                    'kilometraje' => $sale ? $sale->mileage : 0,
                    'repuesto' => $detail->description,
                    'sku' => $detail->product ? ($detail->product->sku ?? $detail->product->code_aux ?? $detail->product->code) : null,
                    'categoria' => $category,
                    'cantidad' => $detail->quantity,
                    'sugerencia' => $nextSuggestion,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $historial
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener historial de repuestos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de repuestos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronizar y generar reposiciones para la venta dada si califica.
     */
    protected function syncReplacementReminders($sale)
    {
        try {
            if (!$sale || $sale->document_type === 'quote' || $sale->status === 'draft' || $sale->status === 'canceled') {
                return;
            }

            // Cargar relaciones si no están cargadas
            $sale->loadMissing(['details.product']);

            $keywords = ['amortiguador', 'pastilla', 'freno', 'aceite', 'filtro', 'aire', 'acondicionado'];

            foreach ($sale->details as $detail) {
                $product = $detail->product;
                if (!$product || $product->item_type != 1) {
                    continue;
                }

                $desc = mb_strtolower($detail->description, 'UTF-8');
                $matches = false;
                foreach ($keywords as $kw) {
                    if (str_contains($desc, $kw)) {
                        $matches = true;
                        break;
                    }
                }

                if ($matches) {
                    // Verificar si ya existe una reposición para esta venta y producto
                    $exists = \App\Models\Sales\RepuestosReposicion::where('sale_id', $sale->id)
                        ->where('product_id', $product->id)
                        ->exists();

                    if (!$exists) {
                        \App\Models\Sales\RepuestosReposicion::create([
                            'product_id' => $product->id,
                            'sku' => $product->sku ?? $product->code_aux ?? $product->code ?? null,
                            'description' => $product->description ?? $product->name ?? $detail->description,
                            'quantity' => $detail->quantity,
                            'purchase_price' => $product->purchase_price ?? 0.00,
                            'supplier_id' => $product->supplier_id,
                            'status' => 'pending',
                            'sale_id' => $sale->id
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en syncReplacementReminders: ' . $e->getMessage());
        }
    }
}

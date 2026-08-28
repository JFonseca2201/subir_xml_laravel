<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Sale;
use App\Services\SequenceService;
use App\Services\Sales\SaleCreationService;
use App\Services\Sales\SaleUpdateService;
use App\Services\Sales\SaleFinanceService;
use App\Services\Sales\SaleDispatchService;
use App\Services\Sales\SalePdfService;
use App\Services\Sales\SaleSriService;
use App\Services\Sales\SaleReminderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class SaleController extends Controller
{
    public function __construct(
        protected SaleCreationService $creationService,
        protected SaleUpdateService $updateService,
        protected SaleFinanceService $financeService,
        protected SaleDispatchService $dispatchService,
        protected SalePdfService $pdfService,
        protected SaleSriService $sriService,
        protected SaleReminderService $reminderService
    ) {}

    /**
     * Obtiene el siguiente secuencial numérico disponible según el tipo de documento.
     */
    public function getNextNumber(Request $request): JsonResponse
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
     * Listar el historial de ventas y cotizaciones con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Sale::with(['client', 'vehicle', 'user', 'workOrder', 'financeRecord.paymentDistributions']);

            // 1. Filtro por búsqueda general
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

            // 2. Filtro por tipo de documento
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

            // 4. Filtro por rango de fechas de atención
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('service_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('service_date', '<=', $request->end_date);
            }

            // 5. Filtro por estado de pago
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            // 6. Excluir cotizaciones del listado de ventas
            if ($request->boolean('exclude_quotes')) {
                $query->where('document_type', '!=', 'quote');
            }

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
     * Registrar una nueva venta, cotización o factura.
     */
    public function store(Request $request): JsonResponse
    {
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
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'payment_distributions' => 'nullable|array',
            'payment_distributions.*.account_id' => 'required|exists:accounts,id',
            'payment_distributions.*.amount' => 'required|numeric|min:0',
            'payment_distributions.*.payment_method' => 'required|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
            'is_draft' => 'nullable|boolean',
        ]);

        try {
            $userId = auth()->id() ?? $request->user_id ?? 1;
            $result = $this->creationService->createSale($request, $userId);
            return response()->json($result['data'], $result['status']);
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

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver el detalle completo de una sola venta o cotización.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $sale = Sale::with([
                'details.product',
                'client',
                'vehicle',
                'technicians',
                'financeRecord.paymentDistributions.account',
                'workOrder'
            ])->find($id);

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
     * Actualizar datos permitidos de una venta o cotización.
     */
    public function update(Request $request, int $id): JsonResponse
    {
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

            $result = $this->updateService->updateSale($sale, $request);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar la venta y revertir sus efectos contables y de stock.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            $result = $this->updateService->deleteSale($sale);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Despachar venta con pago pendiente (salida de bodega).
     */
    public function dispatchSale(Request $request): JsonResponse
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
            $result = $this->dispatchService->dispatchSale($request);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al despachar la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar el pago para una venta pendiente.
     */
    public function registerPayment(Request $request, int $id): JsonResponse
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

            $userId = auth()->id() ?? 1;
            $result = $this->financeService->registerPayment($sale, $request->all(), $userId);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un detalle individual de la venta.
     */
    public function destroyDetail(int $id): JsonResponse
    {
        try {
            $result = $this->updateService->deleteSaleDetail($id);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el ítem de la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte masivo en PDF.
     */
    public function generatePDF(Request $request)
    {
        try {
            return $this->pdfService->generateReportPdf($request);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar / Visualizar PDF individual (RIDE o Nota de Venta / Cotización).
     */
    public function generateSinglePDF(Request $request, int $id)
    {
        try {
            $sale = Sale::with([
                'client',
                'vehicle',
                'user',
                'details.product',
                'technicians',
                'financeRecord.paymentDistributions.account'
            ])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            return $this->pdfService->generateSinglePdf($sale, $request);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Impresión térmica directa a la impresora configurada.
     */
    public function printDirect(int $id): JsonResponse
    {
        try {
            $sale = Sale::with([
                'client',
                'vehicle',
                'user',
                'details.product',
                'technicians',
                'financeRecord.paymentDistributions.account'
            ])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }

            $result = $this->pdfService->printDirect($sale);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la impresión directa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar cotización por correo electrónico con PDF adjunto.
     */
    public function enviarCotizacionPorCorreo(int $id): JsonResponse
    {
        try {
            $sale = Sale::with(['client', 'vehicle'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de cotización no existe.'
                ], 404);
            }

            $result = $this->pdfService->enviarCotizacionPorCorreo($sale);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al despachar el correo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reenviar factura al SRI.
     */
    public function reenviarSri(int $id): JsonResponse
    {
        try {
            $sale = Sale::findOrFail($id);
            $result = $this->sriService->reenviarSri($sale);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar al SRI.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta en tiempo real el estado SRI de una factura.
     */
    public function estadoSri(int $id): JsonResponse
    {
        try {
            $sale = Sale::findOrFail($id);
            $result = $this->sriService->estadoSri($sale);
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el estado SRI.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar XML firmado de la factura electrónica.
     */
    public function descargarXml(int $id)
    {
        try {
            $sale = Sale::with(['client', 'workOrder'])->findOrFail($id);
            return $this->sriService->descargarXml($sale);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el XML.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar RIDE (PDF oficial) de la factura electrónica.
     */
    public function descargarRide(int $id)
    {
        try {
            $sale = Sale::with(['details', 'client', 'vehicle', 'workOrder'])->findOrFail($id);
            return $this->sriService->descargarRide($sale);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el RIDE.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar factura electrónica por correo al cliente.
     */
    public function enviarEmail(Request $request, int $id): JsonResponse
    {
        try {
            $sale = Sale::with(['details', 'client', 'vehicle', 'workOrder'])->findOrFail($id);
            $result = $this->sriService->enviarEmail($sale, $request->input('email'));
            return response()->json($result['data'], $result['status']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir una cotización en venta oficial o factura.
     */
    public function convertQuote(Request $request, int $quoteId): JsonResponse
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
            $result = $this->dispatchService->convertQuote($quote, $request);
            return response()->json($result['data'], $result['status']);
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
     * Obtener el historial de repuestos vendidos para reposición.
     */
    public function getRepuestosHistorial(): JsonResponse
    {
        try {
            $data = $this->reminderService->getRepuestosHistorial();
            return response()->json([
                'success' => true,
                'data' => $data
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
}

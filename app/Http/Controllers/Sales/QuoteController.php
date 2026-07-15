<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Quote;
use App\Models\Sales\Sale;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\Account;
use App\Models\Product\Product as ModelsProduct;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\ProcessElectronicInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class QuoteController extends Controller
{
    /**
     * Get next sequence number for a quote
     */
    public function getNextNumber()
    {
        return response()->json([
            'success' => true,
            'data' => SequenceService::previewNextQuoteNumber()
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Quote::with(['client', 'vehicle', 'user', 'convertedSale']);

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

            // 2. Filtro por cliente específico
            if ($request->has('client_id') && $request->client_id != '') {
                $query->where('client_id', $request->client_id);
            }

            // 3. Filtro por rango de fechas
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('service_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('service_date', '<=', $request->end_date);
            }

            $quotes = $query->orderBy('service_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $quotes
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de cotizaciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|unique:quotes,document_number',
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'mileage' => 'nullable|integer',
            'service_date' => 'required|date',
            'subtotal' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'total' => 'required|numeric',
            'observations' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
        ]);

        try {
            $quote = DB::transaction(function () use ($request) {
                // Consumir número secuencial
                $documentNumber = SequenceService::consumeQuoteNumber($request->document_number);

                $quote = Quote::create([
                    'document_number' => $documentNumber,
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'work_order_id' => $request->work_order_id,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date,
                    'subtotal' => $request->subtotal,
                    'tax_amount' => $request->tax_amount,
                    'total' => $request->total,
                    'status' => 'pending',
                    'observations' => $request->observations,
                    'user_id' => auth()->id() ?? $request->user_id ?? 1,
                ]);

                // Guardar items
                foreach ($request->items as $item) {
                    $quote->details()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0,
                        'total' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0),
                    ]);
                }

                // Sincronizar técnicos
                if ($request->has('technicians') && is_array($request->technicians)) {
                    $quote->technicians()->sync($request->technicians);
                }

                return $quote;
            });

            return response()->json([
                'success' => true,
                'message' => 'Cotización registrada exitosamente.',
                'data' => $quote->load(['client', 'vehicle', 'details'])
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al registrar cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $quote = Quote::with(['client', 'vehicle', 'user', 'details.product', 'technicians', 'convertedSale'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $quote
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'mileage' => 'nullable|integer',
            'service_date' => 'required|date',
            'observations' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'required|numeric',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
        ]);

        try {
            $quote = Quote::findOrFail($id);

            // Validar si ya está convertida
            if ($quote->is_converted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida en venta/factura y no se puede modificar.'
                ], 400);
            }

            // Validar si está anulada
            if ($quote->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una cotización anulada.'
                ], 400);
            }

            DB::transaction(function () use ($quote, $request) {
                // Calcular totales
                $subtotal = 0;
                foreach ($request->items as $item) {
                    $subtotal += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                }
                // Las cotizaciones guardan el subtotal y total igual, o se le aplica el cálculo correspondiente.
                $taxAmount = $request->tax_amount ?? 0.00;
                $total = $subtotal + $taxAmount;

                $quote->update([
                    'client_id' => $request->client_id,
                    'vehicle_id' => $request->vehicle_id,
                    'mileage' => $request->mileage,
                    'service_date' => $request->service_date,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'observations' => $request->observations,
                ]);

                // Actualizar detalles (eliminar viejos y crear nuevos es lo más robusto)
                $quote->details()->delete();
                foreach ($request->items as $item) {
                    $quote->details()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0,
                        'total' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0),
                    ]);
                }

                // Sincronizar técnicos
                if ($request->has('technicians') && is_array($request->technicians)) {
                    $quote->technicians()->sync($request->technicians);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada exitosamente.',
                'data' => $quote->load(['client', 'vehicle', 'details'])
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (Cancel Quote).
     */
    public function destroy($id)
    {
        try {
            $quote = Quote::findOrFail($id);

            if ($quote->is_converted) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede anular una cotización ya convertida.'
                ], 400);
            }

            $quote->update(['status' => 'canceled']);

            return response()->json([
                'success' => true,
                'message' => 'Cotización anulada correctamente.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert quote into a Sale or Invoice
     */
    public function convert(Request $request, int $id)
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
            $quote = Quote::with(['details', 'technicians'])->findOrFail($id);

            // Validaciones de seguridad
            if ($quote->is_converted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida anteriormente.'
                ], 400);
            }

            if ($quote->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede convertir una cotización anulada.'
                ], 400);
            }

            $newSale = null;

            DB::transaction(function () use ($quote, $request, &$newSale) {
                $newDocType = $request->document_type;
                $newDocNumber = SequenceService::consumeGlobalNumber();

                // Recalcular totales (el IVA aplica solo para facturas)
                $subtotal = 0;
                foreach ($quote->details as $detail) {
                    $subtotal += ($detail->quantity * $detail->price) - ($detail->discount ?? 0);
                }
                $taxAmount = $newDocType === 'invoice' ? round($subtotal * 0.15, 2) : 0;
                $total = $subtotal + $taxAmount;

                // Crear nueva venta/factura
                $newSale = Sale::create([
                    'document_type' => $newDocType,
                    'document_number' => $newDocNumber,
                    'client_id' => $quote->client_id,
                    'vehicle_id' => $quote->vehicle_id,
                    'work_order_id' => $quote->work_order_id,
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
                    'user_id' => auth()->id() ?? $quote->user_id ?? 1,
                ]);

                // Copiar los detalles
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

                // Registro financiero
                if ($request->payment_status !== 'pending') {
                    $financeRecord = FinanceRecord::create([
                        'type' => FinanceRecord::TYPE_INCOME,
                        'amount' => $total,
                        'description' => 'Venta (desde cotización): ' . $newDocType . ' - ' . $newDocNumber,
                        'invoice_number' => $newDocNumber,
                        'user_id' => $newSale->user_id,
                        'entry_date' => now()->toDateString(),
                    ]);

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
                        $accountId = 1;
                        if (strtolower($request->payment_method) === 'transferencia') {
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

                // Stock decrement
                foreach ($newSale->details as $detail) {
                    if ($detail->product_id) {
                        $product = ModelsProduct::find($detail->product_id);
                        if ($product && $product->stock !== null) {
                            $product->decrement('stock', $detail->quantity);
                        }
                    }
                }

                // Bloquear la cotización
                $quote->update([
                    'converted_sale_id' => $newSale->id,
                    'status' => 'completed'
                ]);

                // Facturación electrónica si es Factura
                if ($newDocType === 'invoice') {
                    ProcessElectronicInvoice::dispatch($newSale->id)->afterCommit();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Cotización convertida exitosamente.',
                'data' => $newSale->load(['details', 'client', 'vehicle'])
            ], 201);

        } catch (Exception $e) {
            Log::error('Error al convertir cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al convertir la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF representation
     */
    public function generateSinglePDF(int $id)
    {
        try {
            $quote = Quote::with(['client', 'vehicle', 'details.product'])->findOrFail($id);

            $pdf = Pdf::loadView('sales.pdf_quote', ['quote' => $quote]);
            return $pdf->stream('cotizacion_' . $quote->document_number . '.pdf');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send quote PDF via email
     */
    public function enviarCotizacionPorCorreo(int $id)
    {
        try {
            $quote = Quote::with(['client', 'vehicle', 'details.product'])->findOrFail($id);

            if (empty($quote->client->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no tiene un correo registrado.'
                ], 400);
            }

            $data = [
                'titulo_asunto' => 'Presupuesto / Cotización #' . $quote->document_number,
                'cliente' => $quote->client->full_name ?? 'Cliente',
                'mensaje_principal' => 'Adjuntamos la cotización y el presupuesto solicitado para los mantenimientos, servicios o repuestos de tu vehículo. Recuerda que este documento es de carácter informativo.',
                'vehiculo' => $quote->vehicle ? ($quote->vehicle->brand . ' ' . $quote->vehicle->model) : 'N/A',
                'placa' => $quote->vehicle->license_plate ?? 'N/A',
                'accion' => 'Cotización de Servicios'
            ];

            $pdf = Pdf::loadView('sales.pdf_quote', ['quote' => $quote]);
            $pdfRawData = $pdf->output();
            $pdfFileName = 'cotizacion_' . $quote->document_number . '.pdf';

            Mail::to($quote->client->email)->send(
                new \App\Mail\System\TestNotificationMail($data, $pdfRawData, $pdfFileName)
            );

            return response()->json([
                'success' => true,
                'message' => '¡Cotización enviada al correo del cliente con éxito!'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

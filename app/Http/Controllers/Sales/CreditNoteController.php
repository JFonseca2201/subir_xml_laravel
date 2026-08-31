<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessElectronicCreditNote;
use App\Models\Config\Sucursale;
use App\Models\Finance\Account;
use App\Models\Finance\FinancialMovement;
use App\Models\Finance\FinanceRecord;
use App\Models\Product\Product;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Sale;
use App\Services\SequenceService;
use App\Services\SRI\ElectronicInvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreditNoteController extends Controller
{
    public function __construct(
        protected ElectronicInvoiceService $electronicInvoiceService
    ) {}

    /**
     * Listado de Notas de Crédito emitidas.
     */
    public function index(Request $request)
    {
        try {
            $query = CreditNote::with(['sale.client', 'user'])->orderBy('created_at', 'desc');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('document_number', 'like', "%{$search}%")
                      ->orWhere('sri_access_key', 'like', "%{$search}%")
                      ->orWhereHas('sale', function ($sq) use ($search) {
                          $sq->where('document_number', 'like', "%{$search}%")
                             ->orWhereHas('client', function ($cq) use ($search) {
                                 $cq->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('n_document', 'like', "%{$search}%");
                             });
                      });
                });
            }

            $creditNotes = $query->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data'    => $creditNotes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notas de crédito: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Emite una Nota de Crédito Electrónica para una Factura.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id'         => 'required|exists:sales,id',
            'reason'          => 'required|string|max:255',
            'restore_stock'   => 'boolean',
            'reverse_balance' => 'boolean',
        ]);

        $sale = Sale::with(['details.product', 'client', 'financeRecord.paymentDistributions'])->findOrFail($validated['sale_id']);

        if ($sale->document_type !== 'invoice') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden emitir Notas de Crédito sobre Facturas.',
            ], 422);
        }

        // Validar si ya tiene una Nota de Crédito autorizada
        $existingAuthorizedNC = CreditNote::where('sale_id', $sale->id)
            ->where('sri_status', 'AUTORIZADA')
            ->first();

        if ($existingAuthorizedNC) {
            return response()->json([
                'success' => false,
                'message' => "Esta factura ya tiene la Nota de Crédito {$existingAuthorizedNC->document_number} autorizada.",
            ], 422);
        }

        try {
            $creditNote = DB::transaction(function () use ($validated, $sale) {
                // 1. Obtener secuencial
                $docNumber = SequenceService::consumeNumber('credit_note');

                // 2. Crear registro de Nota de Crédito
                $creditNote = CreditNote::create([
                    'sale_id'         => $sale->id,
                    'document_number' => $docNumber,
                    'reason'          => $validated['reason'],
                    'subtotal'        => $sale->subtotal,
                    'tax_amount'      => $sale->tax_amount,
                    'total'           => $sale->total,
                    'restore_stock'   => $validated['restore_stock'] ?? true,
                    'reverse_balance' => $validated['reverse_balance'] ?? true,
                    'user_id'         => Auth::id() ?? $sale->user_id,
                    'sri_status'      => 'CREADA',
                ]);

                // 3. Reversión de stock (si aplica)
                if ($creditNote->restore_stock) {
                    foreach ($sale->details as $detail) {
                        if ($detail->product_id) {
                            $product = Product::find($detail->product_id);
                            if ($product) {
                                $product->increment('stock', $detail->quantity);
                                Log::info("[NC] Stock restaurado para producto #{$product->id} (+{$detail->quantity})");
                            }
                        }
                    }
                }

                // 4. Reversión contable en cuentas (si aplica)
                if ($creditNote->reverse_balance && $sale->financeRecord) {
                    $finRecord = $sale->financeRecord;
                    if ($finRecord->paymentDistributions && $finRecord->paymentDistributions->count() > 0) {
                        foreach ($finRecord->paymentDistributions as $dist) {
                            if ($dist->account_id && $dist->amount > 0) {
                                $account = Account::find($dist->account_id);
                                if ($account) {
                                    $account->decrement('saldo_actual', $dist->amount);
                                    Log::info("[NC] Saldo revertido en cuenta #{$account->id} (-{$dist->amount})");
                                }
                            }
                        }
                    }
                }

                return $creditNote;
            });

            // 5. Procesar inmediatamente o despachar al job
            try {
                $this->electronicInvoiceService->procesarNotaCredito($creditNote);
                $creditNote->refresh();
            } catch (Exception $sriEx) {
                Log::warning("[NC Controller] Procesamiento inmediato retornó: " . $sriEx->getMessage() . ". Encolando job de respaldo.");
                ProcessElectronicCreditNote::dispatch($creditNote->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nota de Crédito generada y procesada ante el SRI exitosamente.',
                'data'    => $creditNote->fresh(['sale.client']),
            ], 201);

        } catch (Exception $e) {
            Log::error("[NC Controller] Error al generar Nota de Crédito: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la Nota de Crédito: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una Nota de Crédito específica.
     */
    public function show($id)
    {
        $creditNote = CreditNote::with(['sale.client', 'sale.details.product', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $creditNote,
        ]);
    }

    /**
     * Reenvía la Nota de Crédito al SRI.
     */
    public function resendSri($id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->sri_status === 'AUTORIZADA') {
            return response()->json([
                'success' => false,
                'message' => 'Esta Nota de Crédito ya está autorizada por el SRI.',
            ], 422);
        }

        try {
            $this->electronicInvoiceService->procesarNotaCredito($creditNote);
            $creditNote->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Nota de Crédito procesada.',
                'data'    => $creditNote,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar al SRI: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga el XML firmado de la Nota de Crédito.
     */
    public function descargarXml($id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if (!$creditNote->xml_path || !Storage::exists($creditNote->xml_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El XML de esta Nota de Crédito no está disponible.',
            ], 404);
        }

        $filename = 'NC_' . ($creditNote->document_number ?: $creditNote->id) . '.xml';

        return response(Storage::get($creditNote->xml_path), 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Descarga el RIDE PDF de la Nota de Crédito.
     */
    public function descargarRide($id)
    {
        $creditNote = CreditNote::with(['sale.client', 'sale.details.product'])->findOrFail($id);

        if (!$creditNote->pdf_path || !Storage::exists($creditNote->pdf_path)) {
            // Si aún no se generó el PDF, generarlo al vuelo
            $sucursal = Sucursale::first();
            $autorizacion = [
                'numeroAutorizacion' => $creditNote->sri_access_key,
                'fechaAutorizacion'  => $creditNote->sri_authorization_date ? $creditNote->sri_authorization_date->format('d/m/Y H:i:s') : null,
                'estado'             => $creditNote->sri_status,
            ];
            $ridePath = $this->electronicInvoiceService->generarRideNotaCredito($creditNote, $sucursal, $autorizacion);
            $creditNote->update(['pdf_path' => $ridePath]);
        }

        $filename = 'RIDE_NC_' . ($creditNote->document_number ?: $creditNote->id) . '.pdf';

        return response(Storage::get($creditNote->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}

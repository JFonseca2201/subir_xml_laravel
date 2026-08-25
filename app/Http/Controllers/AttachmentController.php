<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\InvoiceStorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    protected InvoiceStorageService $storageService;

    public function __construct(InvoiceStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Mapeo de alias cortos a clases de modelos para simplificar el frontend.
     */
    protected function resolveModelClass(string $type): string
    {
        $map = [
            'sale' => \App\Models\Sales\Sale::class,
            'sales' => \App\Models\Sales\Sale::class,
            'work_order' => \App\Models\WorkOrder\WorkOrder::class,
            'work_orders' => \App\Models\WorkOrder\WorkOrder::class,
            'expense' => \App\Models\Finance\FinanceRecord::class,
            'expenses' => \App\Models\Finance\FinanceRecord::class,
            'finance_record' => \App\Models\Finance\FinanceRecord::class,
            'financial_movement' => \App\Models\Finance\FinancialMovement::class,
            'invoice' => \App\Models\Invoice\Invoice::class,
            'invoices' => \App\Models\Invoice\Invoice::class,
            'purchase' => \App\Models\Invoice\Invoice::class,
            'partner' => \App\Models\Partner\Partner::class,
            'transfer' => \App\Models\Finance\InternalTransfer::class,
            'transfers' => \App\Models\Finance\InternalTransfer::class,
            'internal_transfer' => \App\Models\Finance\InternalTransfer::class,
            'aporte' => \App\Models\Partner\AporteCapital::class,
            'aportes' => \App\Models\Partner\AporteCapital::class,
            'aporte_capital' => \App\Models\Partner\AporteCapital::class,
            'partner_contribution' => \App\Models\Partner\AporteCapital::class,
            'employee_payment' => \App\Models\Employee\EmployeePayment::class,
            'employee_payments' => \App\Models\Employee\EmployeePayment::class,
            'employee_advance' => \App\Models\Employee\EmployeeAdvance::class,
            'employee_advances' => \App\Models\Employee\EmployeeAdvance::class,
            'nomina' => \App\Models\Employee\EmployeePayment::class,
            'client' => \App\Models\Client\Client::class,
            'vehicle' => \App\Models\Vehicles\Vehicle::class,
        ];

        $clean = strtolower(trim($type));
        return $map[$clean] ?? $type;
    }

    /**
     * Lista los adjuntos de un modelo específico con resolución inteligente de relaciones vinculadas.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'attachable_type' => 'required|string',
            'attachable_id' => 'required|integer',
            'identifier' => 'nullable|string',
        ]);

        $modelClass = $this->resolveModelClass($request->attachable_type);
        $attachableId = (int) $request->attachable_id;
        $identifier = $request->get('identifier');

        $allAttachments = collect();

        $typeCandidates = [
            $modelClass,
            class_basename($modelClass),
            'App\\Models\\' . class_basename($modelClass),
        ];
        if (class_exists($modelClass)) {
            $typeCandidates[] = (new $modelClass)->getMorphClass();
        }
        $typeCandidates = array_values(array_unique(array_filter($typeCandidates)));

        // 1. Búsqueda directa por modelo y id
        $direct = Attachment::whereIn('attachable_type', $typeCandidates)
            ->where('attachable_id', $attachableId)
            ->get();
        $allAttachments = $allAttachments->concat($direct);

        // 2. Si es FinanceRecord o FinancialMovement
        if ($modelClass === \App\Models\Finance\FinanceRecord::class || $modelClass === \App\Models\Finance\FinancialMovement::class) {
            // Si el ID es de un FinanceRecord, buscar adjuntos en movimientos vinculados
            $linkedMovements = \App\Models\Finance\FinancialMovement::where('metadata->finance_record_id', $attachableId)->pluck('id');
            if ($linkedMovements->isNotEmpty()) {
                $movementAtts = Attachment::whereIn('attachable_type', [\App\Models\Finance\FinancialMovement::class, 'App\Models\FinancialMovement'])
                    ->whereIn('attachable_id', $linkedMovements)
                    ->get();
                $allAttachments = $allAttachments->concat($movementAtts);
            }

            // Si el ID es de un FinancialMovement, buscar en el FinanceRecord vinculado
            $movement = \App\Models\Finance\FinancialMovement::find($attachableId);
            if ($movement) {
                $finRecordId = $movement->metadata['finance_record_id'] ?? null;
                if (!$finRecordId && $movement->movable_type === \App\Models\Finance\PaymentDistribution::class) {
                    $dist = \App\Models\Finance\PaymentDistribution::find($movement->movable_id);
                    $finRecordId = $dist?->finance_record_id;
                }
                if ($finRecordId) {
                    $frAtts = Attachment::whereIn('attachable_type', [\App\Models\Finance\FinanceRecord::class, 'App\Models\FinanceRecord'])
                        ->where('attachable_id', $finRecordId)
                        ->get();
                    $allAttachments = $allAttachments->concat($frAtts);
                }

                // Si está vinculado a Sale, WorkOrder, InternalTransfer, etc.
                if ($movement->movable_type && $movement->movable_id) {
                    $movableAtts = Attachment::where('attachable_type', $movement->movable_type)
                        ->where('attachable_id', $movement->movable_id)
                        ->get();
                    $allAttachments = $allAttachments->concat($movableAtts);
                }
            }
        }

        // 4. Búsqueda por identifier sólo dentro del mismo modelo
        if (!empty($identifier) && strlen(trim($identifier)) >= 3) {
            $cleanId = trim($identifier);
            $byIdentifier = Attachment::where('attachable_type', $modelClass)
                ->where(function ($q) use ($cleanId) {
                    $q->where('metadata->identifier', $cleanId)
                      ->orWhere('file_name', 'like', "{$cleanId}_%");
                })->get();
            $allAttachments = $allAttachments->concat($byIdentifier);
        }

        $uniqueAttachments = $allAttachments->unique('id')->sortBy('created_at')->values();

        return response()->json([
            'status' => 'success',
            'data' => $uniqueAttachments,
            'count' => $uniqueAttachments->count(),
        ], 200);
    }

    /**
     * Carga y adjunta múltiples comprobantes/archivos a un modelo existente.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'attachable_type' => 'required|string',
            'attachable_id' => 'required|integer',
            'receipts' => 'required|array|min:1',
            'receipts.*' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:15360', // 15MB max por archivo
            'type' => 'nullable|string',
            'identifier' => 'nullable|string',
            'party_name' => 'nullable|string',
            'date' => 'nullable|date',
        ]);

        $modelClass = $this->resolveModelClass($request->attachable_type);

        if (!class_exists($modelClass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tipo de modelo no reconocido: ' . $request->attachable_type,
            ], 422);
        }

        $model = $modelClass::find($request->attachable_id);

        // Si no encontró el modelo directo pero es FinanceRecord y el ID era de FinancialMovement
        if (!$model && $modelClass === \App\Models\Finance\FinanceRecord::class) {
            $movement = \App\Models\Finance\FinancialMovement::find($request->attachable_id);
            if ($movement) {
                $finRecordId = $movement->metadata['finance_record_id'] ?? null;
                if (!$finRecordId && $movement->movable_type === \App\Models\Finance\PaymentDistribution::class) {
                    $dist = \App\Models\Finance\PaymentDistribution::find($movement->movable_id);
                    $finRecordId = $dist?->finance_record_id;
                }
                if ($finRecordId) {
                    $model = \App\Models\Finance\FinanceRecord::find($finRecordId);
                }
            }
        }

        if (!$model) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registro no encontrado para adjuntar comprobantes.',
            ], 404);
        }

        // Resolver identificador
        $identifier = $request->identifier;
        if (!$identifier) {
            $identifier = $model->number ?? $model->document_number ?? $model->invoice_number ?? $model->id;
        }

        // Resolver nombre del cliente/proveedor/tercero
        $partyName = $request->party_name;
        if (!$partyName) {
            if (isset($model->client) && $model->client) {
                $partyName = $model->client->full_name ?: trim(($model->client->name ?? '') . ' ' . ($model->client->surname ?? ''));
            } elseif (isset($model->supplier) && $model->supplier) {
                $partyName = $model->supplier->name ?? $model->supplier->company_name ?? 'PROVEEDOR';
            } elseif (isset($model->beneficiary) && $model->beneficiary) {
                $partyName = $model->beneficiary;
            } elseif (isset($model->partner) && $model->partner) {
                $partyName = $model->partner->name ?? 'SOCIO';
            } else {
                $partyName = 'GENERAL';
            }
        }

        $date = $request->date ? Carbon::parse($request->date) : ($model->created_at ?? Carbon::now());
        $type = $request->get('type', 'receipt');

        $receiptFiles = $request->file('receipts', []);

        $savedAttachments = $this->storageService->attachReceiptsToModel(
            $model,
            $identifier,
            $partyName,
            $receiptFiles,
            $date,
            $type
        );

        return response()->json([
            'status' => 'success',
            'message' => count($savedAttachments) . ' comprobante(s) adjuntado(s) correctamente.',
            'data' => $savedAttachments,
        ], 201);
    }

    /**
     * Ver/previsualizar archivo en navegador (PDF o Imagen).
     */
    public function view(int $id)
    {
        $attachment = Attachment::findOrFail($id);

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json(['status' => 'error', 'message' => 'Archivo no encontrado en el almacenamiento.'], 404);
        }

        $fullPath = Storage::disk('public')->path($attachment->file_path);
        $mime = $attachment->mime_type ?: Storage::disk('public')->mimeType($attachment->file_path);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
        ]);
    }

    /**
     * Descargar archivo adjunto.
     */
    public function download(int $id)
    {
        $attachment = Attachment::findOrFail($id);

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json(['status' => 'error', 'message' => 'Archivo no encontrado.'], 404);
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Eliminar archivo adjunto física y lógicamente.
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Comprobante eliminado correctamente.',
        ], 200);
    }
}

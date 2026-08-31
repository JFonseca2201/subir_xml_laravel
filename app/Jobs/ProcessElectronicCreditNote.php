<?php

namespace App\Jobs;

use App\Models\Sales\CreditNote;
use App\Services\SRI\ElectronicInvoiceService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessElectronicCreditNote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 120;

    /**
     * @param int $creditNoteId ID de la Nota de Crédito
     */
    public function __construct(public readonly int $creditNoteId)
    {
        $this->onQueue('default');
    }

    /**
     * Ejecuta el job: orquesta el envío de la Nota de Crédito al SRI.
     */
    public function handle(ElectronicInvoiceService $service): void
    {
        $creditNote = CreditNote::find($this->creditNoteId);

        if (!$creditNote) {
            Log::error("[SRI-NC Job] Nota de Crédito #{$this->creditNoteId} no encontrada.");
            return;
        }

        if ($creditNote->sri_status === 'AUTORIZADA') {
            Log::info("[SRI-NC Job] NC #{$this->creditNoteId} ya está AUTORIZADA. Se omite.");
            return;
        }

        Log::info("[SRI-NC Job] Procesando NC #{$this->creditNoteId}. Intento: {$this->attempts()}");

        try {
            $service->procesarNotaCredito($creditNote);
        } catch (Exception $e) {
            Log::error("[SRI-NC Job] Error procesando NC #{$this->creditNoteId}: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                $creditNote->update([
                    'sri_status' => 'DEVUELTA',
                    'sri_error'  => 'Error tras varios intentos: ' . $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}

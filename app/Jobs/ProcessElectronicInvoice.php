<?php

namespace App\Jobs;

use App\Models\Sales\Sale;
use App\Services\SRI\ElectronicInvoiceService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessElectronicInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos antes de marcar el job como fallido.
     */
    public int $tries = 3;

    /**
     * Segundos de espera entre reintentos (backoff exponencial).
     */
    public array $backoff = [60, 300, 900];

    /**
     * Segundos máximos de ejecución antes de timeout.
     */
    public int $timeout = 120;

    /**
     * @param int $saleId ID de la venta a procesar
     */
    public function __construct(public readonly int $saleId)
    {
        $this->onQueue('default');
    }

    /**
     * Ejecuta el job: llama al orquestador de facturación electrónica.
     */
    public function handle(ElectronicInvoiceService $service): void
    {
        $sale = Sale::find($this->saleId);

        if (!$sale) {
            Log::error("[SRI Job] Venta #{$this->saleId} no encontrada.");
            return;
        }

        // Evitar reprocesar facturas ya autorizadas
        if ($sale->sri_status === 'AUTORIZADA') {
            Log::info("[SRI Job] Venta #{$this->saleId} ya está AUTORIZADA. Se omite.");
            return;
        }

        Log::info("[SRI Job] Procesando venta #{$this->saleId}. Intento: {$this->attempts()}");

        try {
            $service->procesar($sale);
        } catch (Exception $e) {
            Log::error("[SRI Job] Error en venta #{$this->saleId}: " . $e->getMessage());

            $sale->update([
                'sri_status' => 'RECHAZADA',
                'sri_error'  => $e->getMessage(),
            ]);

            // Solo relanzar para reintentar si es una cola asíncrona real (no 'sync') y quedan intentos
            if (config('queue.default') !== 'sync' && $this->attempts() < $this->tries) {
                throw $e;
            }

            Log::error("[SRI Job] Venta #{$this->saleId} marcada como RECHAZADA.");
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sales\Sale;
use App\Services\SRI\ElectronicInvoiceService;
use App\Services\SRI\SriWebServiceService;
use App\Jobs\ProcessElectronicInvoice;
use Illuminate\Support\Facades\Log;

class ConsultarFacturasPendientesSri extends Command
{
    /**
     * El nombre y firma del comando.
     *
     * @var string
     */
    protected $signature = 'sri:consultar-pendientes';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Consulta el estado de autorización de las facturas enviadas o pendientes ante el SRI';

    /**
     * Ejecuta el comando.
     */
    public function handle(SriWebServiceService $sriWs, ElectronicInvoiceService $invoiceService): int
    {
        $this->info('Consultando facturas pendientes ante el SRI...');

        // Buscar ventas en estado ENVIADA o CREADA
        $pendingSales = Sale::where('document_type', 'invoice')
            ->whereIn('sri_status', ['ENVIADA', 'CREADA', 'FIRMADA'])
            ->whereNotNull('sri_access_key')
            ->get();

        if ($pendingSales->isEmpty()) {
            $this->info('No hay facturas pendientes por autorizar.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($pendingSales as $sale) {
            $this->line("Procesando Factura #{$sale->document_number} (ID: {$sale->id}) - Estado: {$sale->sri_status}");

            try {
                if ($sale->sri_status === 'CREADA' || $sale->sri_status === 'FIRMADA') {
                    // Reenviar a flujo completo
                    ProcessElectronicInvoice::dispatch($sale->id);
                    $this->info(" -> Job encolado para reenvío completo.");
                } else {
                    // Consultar autorización en el SRI
                    $autorizacion = $sriWs->autorizarComprobante($sale->sri_access_key);
                    $this->line(" -> Respuesta SRI: " . $autorizacion['estado']);

                    if ($autorizacion['estado'] === 'AUTORIZADA') {
                        // Procesar autorización y RIDE mediante el servicio principal
                        $invoiceService->procesar($sale);
                        $this->info(" -> Factura #{$sale->document_number} ¡AUTORIZADA EXITOSAMENTE!");
                        $count++;
                    } elseif ($autorizacion['estado'] === 'NO_AUTORIZADA') {
                        $errores = implode(' | ', $autorizacion['errores']);
                        $sale->update([
                            'sri_status' => 'RECHAZADA',
                            'sri_error'  => $errores,
                        ]);
                        $this->error(" -> Factura RECHAZADA por SRI: {$errores}");
                    }
                }
            } catch (\Exception $e) {
                $this->error(" -> Error al consultar factura #{$sale->id}: " . $e->getMessage());
                Log::error("[SRI Command Error] Factura #{$sale->id}: " . $e->getMessage());
            }
        }

        $this->info("Proceso completado. Facturas autorizadas en esta corrida: {$count}");
        return Command::SUCCESS;
    }
}

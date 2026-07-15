<?php

namespace App\Services\SRI;

use App\Models\Sales\Sale;
use App\Models\Config\Sucursale;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestador principal del flujo de Facturación Electrónica SRI Ecuador.
 *
 * Flujo:
 *  1. Calcular subtotales por tarifa IVA
 *  2. Generar XML  (XmlGeneratorService)
 *  3. Firmar XML   (FirmaElectronicaService)
 *  4. Guardar XML  en storage/app/xmls/
 *  5. Enviar al SRI (SriWebServiceService::enviarComprobante)
 *  6. Si RECIBIDA → consultar autorización
 *  7. Si AUTORIZADA → generar RIDE PDF y guardar
 *  8. Actualizar registro en BD
 */
class ElectronicInvoiceService
{
    public function __construct(
        private XmlGeneratorService    $xmlGenerator,
        private FirmaElectronicaService $firmaService,
        private SriWebServiceService   $sriWs
    ) {}

    /**
     * Punto de entrada principal. Procesa una venta como factura electrónica.
     *
     * @throws Exception
     */
    public function procesar(Sale $sale): void
    {
        // Cargar relaciones necesarias
        $sale->load(['details.product', 'client', 'vehicle', 'user']);

        $sucursal = $this->obtenerSucursal($sale);

        // ── Paso 1: Calcular subtotales por tarifa ───────────────────────
        $subtotales = $this->calcularSubtotalesPorTarifa($sale);
        $sale->update($subtotales);

        // ── Paso 2: Generar XML ──────────────────────────────────────────
        $xmlString = $this->xmlGenerator->generar($sale->fresh(['details', 'client']), $sucursal);
        Log::info("[SRI] XML generado para venta #{$sale->id}");

        // ── Paso 3: Firmar XML ───────────────────────────────────────────
        $p12Path  = storage_path('app/' . ltrim($sucursal->firma_electronica, '/'));
        $xmlFirmado = $this->firmaService->firmar($xmlString, $p12Path, $sucursal->password_firma ?? '');
        Log::info("[SRI] XML firmado para venta #{$sale->id}");

        // ── Paso 4: Guardar XML firmado ──────────────────────────────────
        $xmlPath = $this->guardarArchivo($xmlFirmado, $sale->sri_access_key, 'xmls', 'xml');
        $sale->update([
            'xml_path'   => $xmlPath,
            'sri_status' => 'FIRMADA',
        ]);

        // ── Paso 5: Enviar al SRI ────────────────────────────────────────
        $sale->update(['sri_status' => 'ENVIADA']);
        $recepcion = $this->sriWs->enviarComprobante($xmlFirmado);
        Log::info("[SRI] Respuesta recepción venta #{$sale->id}: " . $recepcion['estado']);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $errores = implode(' | ', $recepcion['errores']);
            $sale->update([
                'sri_status' => 'DEVUELTA',
                'sri_error'  => $errores,
            ]);
            throw new Exception("SRI devolvió el comprobante: {$errores}");
        }

        // ── Paso 6: Consultar autorización (esperar 3 segundos) ──────────
        sleep(3);
        $autorizacion = $this->sriWs->autorizarComprobante($sale->sri_access_key);
        Log::info("[SRI] Respuesta autorización venta #{$sale->id}: " . $autorizacion['estado']);

        if ($autorizacion['estado'] === 'AUTORIZADA') {
            // ── Paso 7: Generar RIDE PDF ─────────────────────────────────
            $ridePath = $this->generarRide($sale->fresh(['details', 'client', 'vehicle']), $sucursal, $autorizacion);

            $sale->update([
                'sri_status'             => 'AUTORIZADA',
                'sri_authorization_date' => $autorizacion['fechaAutorizacion'],
                'sri_error'              => null,
                'pdf_path'               => $ridePath,
            ]);

            Log::info("[SRI] Factura #{$sale->id} AUTORIZADA. Clave: {$sale->sri_access_key}");

        } elseif ($autorizacion['estado'] === 'EN_PROCESO') {
            // El SRI puede demorar; dejar en ENVIADA para reintentar luego
            Log::warning("[SRI] Venta #{$sale->id} aún EN_PROCESO en el SRI.");
            $sale->update(['sri_status' => 'ENVIADA']);

        } else {
            $errores = implode(' | ', $autorizacion['errores']);
            $sale->update([
                'sri_status' => 'RECHAZADA',
                'sri_error'  => $errores,
            ]);
            throw new Exception("SRI rechazó el comprobante: {$errores}");
        }
    }

    /**
     * Calcula los subtotales por tarifa de IVA desde los detalles de la venta.
     */
    public function calcularSubtotalesPorTarifa(Sale $sale): array
    {
        $subtotals = [
            'subtotal_iva_15'    => 0.0,
            'subtotal_iva_0'     => 0.0,
            'subtotal_no_objeto' => 0.0,
            'subtotal_exento'    => 0.0,
        ];

        foreach ($sale->details as $detalle) {
            $base = ((float)$detalle->quantity * (float)$detalle->price) - (float)$detalle->discount;
            $rate = (float)$detalle->tax_rate;

            if ($rate == 15) {
                $subtotals['subtotal_iva_15'] += $base;
            } elseif ($rate == 0) {
                $subtotals['subtotal_iva_0'] += $base;
            } elseif ($rate < 0) { // Códigos especiales como -1 para 'No Objeto'
                $subtotals['subtotal_no_objeto'] += $base;
            } else {
                // Agrupar otras tarifas de IVA (ej. 12%) en el subtotal 0 por simplicidad,
                // aunque el XML las separará correctamente. O podrías añadir más campos si es necesario.
                // Para la mayoría de casos actuales (0 y 15), esto es suficiente.
            }
        }

        return array_map(fn($value) => round($value, 2), $subtotals);
    }

    /**
     * Genera el RIDE (PDF) de la factura electrónica.
     */
    private function generarRide(Sale $sale, Sucursale $sucursal, array $autorizacion): string
    {
        $pdf = Pdf::loadView('pdf.ride', [
            'sale'         => $sale,
            'sucursal'     => $sucursal,
            'autorizacion' => $autorizacion,
        ])->setPaper('A4', 'portrait');

        $content  = $pdf->output();
        return $this->guardarArchivo($content, $sale->sri_access_key, 'rides', 'pdf');
    }

    /**
     * Guarda un archivo en storage/app/{directorio}/{año}/{mes}/{nombre}.{ext}
     * y retorna la ruta relativa.
     */
    private function guardarArchivo(string $contenido, string $nombre, string $directorio, string $ext): string
    {
        $año  = now()->format('Y');
        $mes  = now()->format('m');
        $path = "{$directorio}/{$año}/{$mes}/{$nombre}.{$ext}";

        Storage::put($path, $contenido);

        return $path;
    }

    /**
     * Obtiene la sucursal del emisor asociada a la venta.
     */
    private function obtenerSucursal(Sale $sale): Sucursale
    {
        // Intentar obtener sucursal del cliente o del usuario
        $sucursalId = $sale->client->sucursale_id
            ?? optional($sale->user)->sucursale_id
            ?? 1;

        $sucursal = Sucursale::find($sucursalId) ?? Sucursale::first();

        if (!$sucursal) {
            throw new Exception('No se encontró una sucursal configurada para emitir facturas electrónicas.');
        }

        if (empty($sucursal->ruc)) {
            throw new Exception('La sucursal no tiene RUC configurado.');
        }

        if (empty($sucursal->firma_electronica)) {
            throw new Exception('La sucursal no tiene firma electrónica (.p12) configurada.');
        }

        return $sucursal;
    }
}
<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Config\Sucursale;
use App\Jobs\ProcessElectronicInvoice;
use App\Services\SRI\ElectronicInvoiceService;
use Illuminate\Support\Facades\Storage;
use Exception;

class SaleSriService
{
    public function __construct(
        protected SalePdfService $pdfService,
        protected ElectronicInvoiceService $electronicInvoiceService
    ) {}

    /**
     * Reenvía al SRI una factura que fue DEVUELTA o RECHAZADA.
     */
    public function reenviarSri(Sale $sale): array
    {
        if ($sale->document_type !== 'invoice') {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => 'Solo las facturas pueden enviarse al SRI.',
                ]
            ];
        }

        if ($sale->sri_status === 'AUTORIZADA') {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => 'Esta factura ya está autorizada por el SRI.',
                ]
            ];
        }

        ProcessElectronicInvoice::dispatch($sale->id);
        $sale->update(['sri_status' => 'CREADA', 'sri_error' => null]);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Factura encolada para reenvío al SRI.',
                'data'    => $sale->only(['id', 'document_number', 'sri_status']),
            ]
        ];
    }

    /**
     * Consulta en tiempo real el estado SRI de una factura electrónica.
     */
    public function estadoSri(Sale $sale): array
    {
        $data = Sale::select([
            'id',
            'document_number',
            'document_type',
            'sri_access_key',
            'sri_status',
            'sri_authorization_date',
            'sri_error',
            'xml_path',
            'pdf_path',
        ])->find($sale->id);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'data'    => $data,
            ]
        ];
    }

    /**
     * Descarga el XML firmado de la factura electrónica.
     */
    public function descargarXml(Sale $sale)
    {
        if (!$sale->xml_path || !Storage::exists($sale->xml_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El XML de esta factura aún no está disponible.',
            ], 404);
        }

        $filename = $this->pdfService->buildDownloadFileName($sale, 'xml');

        return response(Storage::get($sale->xml_path), 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"; filename*=UTF-8''" . rawurlencode($filename),
        ]);
    }

    /**
     * Descarga el RIDE (PDF de representación impresa) de la factura electrónica.
     */
    public function descargarRide(Sale $sale)
    {
        $sale->loadMissing(['details', 'client', 'vehicle', 'workOrder']);

        $sucursal = Sucursale::find($sale->client->sucursale_id ?? 1) ?? Sucursale::first();
        $autorizacion = [
            'numeroAutorizacion' => $sale->sri_access_key,
            'fechaAutorizacion'  => $sale->sri_authorization_date ? $sale->sri_authorization_date->format('d/m/Y H:i:s') : null,
            'estado'             => $sale->sri_status,
        ];

        $ridePath = $this->electronicInvoiceService->generarRide($sale, $sucursal, $autorizacion);
        $sale->update(['pdf_path' => $ridePath]);

        $filename = $this->pdfService->buildDownloadFileName($sale, 'pdf');

        return response(Storage::get($ridePath), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"; filename*=UTF-8''" . rawurlencode($filename),
        ]);
    }

    /**
     * Envía o reenvía la factura electrónica por correo al cliente.
     */
    public function enviarEmail(Sale $sale, ?string $emailDestino): array
    {
        if ($sale->document_type !== 'invoice') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Solo se pueden enviar por correo facturas electrónicas.',
                ]
            ];
        }

        $email = $emailDestino ?: ($sale->client->email ?? null);
        if (empty($email)) {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => 'El cliente no tiene un correo electrónico configurado.',
                ]
            ];
        }

        if (!$sale->pdf_path || !Storage::exists($sale->pdf_path)) {
            $this->electronicInvoiceService->procesar($sale);
            $sale->refresh();
        }

        $sent = $this->electronicInvoiceService->enviarPorCorreo($sale, $email);

        if ($sent) {
            return [
                'status' => 200,
                'data' => [
                    'success' => true,
                    'message' => "Factura enviada exitosamente a {$email}",
                ]
            ];
        }

        return [
            'status' => 500,
            'data' => [
                'success' => false,
                'message' => 'No se pudo enviar el correo. Verifique la configuración SMTP.',
            ]
        ];
    }
}

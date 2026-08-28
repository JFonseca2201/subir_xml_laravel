<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Config\Sucursale;
use App\Helpers\PdfHelper;
use App\Mail\System\TestNotificationMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Exception;

class SalePdfService
{
    /**
     * Construye el nombre de archivo para descargas en formato: "{OT} {APELLIDOS NOMBRES}.{ext}"
     * Ejemplo: "01613 AYALA MUÑOZ NELSON ARTURO.pdf"
     */
    public function buildDownloadFileName(Sale $sale, string $extension): string
    {
        $ot = $sale->work_order_number ?: ($sale->workOrder ? $sale->workOrder->number : null);
        $prefix = $ot ? trim((string)$ot) : ($sale->document_number ? trim((string)$sale->document_number) : 'DOC');

        $client = $sale->client;
        $clientName = '';
        if ($client) {
            $surname = trim((string)($client->surname ?? ''));
            $name = trim((string)($client->name ?? ''));
            if (!empty($surname) || !empty($name)) {
                $clientName = trim("{$surname} {$name}");
            } else {
                $clientName = trim((string)($client->full_name ?? ''));
            }
        }
        $clientName = $clientName ?: 'CLIENTE';

        $fullName = trim("{$prefix} {$clientName}");
        $cleanName = preg_replace('~[\\\\/:*?"<>|]+~u', '', $fullName);
        $cleanName = preg_replace('/\s+/', ' ', $cleanName);
        $cleanName = trim($cleanName);

        return "{$cleanName}.{$extension}";
    }

    /**
     * Genera el reporte PDF del listado de ventas según filtros.
     */
    public function generateReportPdf(Request $request)
    {
        $query = Sale::with(['client', 'vehicle', 'user', 'details']);

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

        if ($request->has('document_type') && $request->document_type != '') {
            $query->where('document_type', $request->document_type);
        }

        if ($request->has('client_id') && $request->client_id != '') {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('service_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('service_date', '<=', $request->end_date);
        }

        if ($request->has('payment_status') && $request->payment_status != '') {
            $query->where('payment_status', $request->payment_status);
        }

        $sales = $query->orderBy('service_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $vehicleBrands = config('vehicle_brands', []);
        foreach ($sales as $sale) {
            if ($sale->vehicle && isset($sale->vehicle->brand)) {
                $brandId = $sale->vehicle->brand;
                $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
            }
        }

        $pdf = Pdf::loadView('sales.pdf_list', compact('sales'));
        return $pdf->download('ventas_' . date('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Genera o previsualiza el PDF individual de una venta / factura / cotización.
     */
    public function generateSinglePdf(Sale $sale, Request $request)
    {
        $vehicleBrands = config('vehicle_brands', []);
        if ($sale->vehicle && isset($sale->vehicle->brand)) {
            $brandId = $sale->vehicle->brand;
            $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
        }

        if ($sale->document_type === 'invoice') {
            $sucursal = Sucursale::find($sale->client->sucursale_id ?? 1) ?? Sucursale::first();
            $autorizacion = [
                'numeroAutorizacion' => $sale->sri_access_key,
                'fechaAutorizacion'  => $sale->sri_authorization_date ? $sale->sri_authorization_date->format('d/m/Y H:i:s') : null,
                'estado'             => $sale->sri_status,
            ];

            if ($request->has('print')) {
                return view('pdf.ride', compact('sale', 'sucursal', 'autorizacion'));
            }
            $pdf = Pdf::loadView('pdf.ride', compact('sale', 'sucursal', 'autorizacion'));
            $fileName = $this->buildDownloadFileName($sale, 'pdf');
            return $pdf->stream($fileName);
        }

        if ($request->has('print')) {
            return view('sales.pdf_sale', compact('sale'));
        }
        $pdf = Pdf::loadView('sales.pdf_sale', compact('sale'));
        $fileName = PdfHelper::formatFileName($sale->document_type, $sale->document_number, $sale->client, $sale->vehicle);
        return $pdf->stream($fileName);
    }

    /**
     * Impresión térmica directa a la impresora de Windows.
     */
    public function printDirect(Sale $sale): array
    {
        $vehicleBrands = config('vehicle_brands', []);
        if ($sale->vehicle && isset($sale->vehicle->brand)) {
            $brandId = $sale->vehicle->brand;
            $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
        }

        $pdf = Pdf::loadView('sales.pdf_sale', compact('sale'));
        $tempFileName = 'temp_sale_' . $sale->id . '_' . time() . '.pdf';
        $tempPath = storage_path('app/' . $tempFileName);
        $pdf->save($tempPath);

        $printerName = env('PRINTER_NAME', 'L5290 Series(Network)');
        $edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

        if (file_exists($edgePath)) {
            $command = sprintf(
                'start /B "" "%s" --headless --print-to-printer="%s" "%s"',
                $edgePath,
                $printerName,
                $tempPath
            );
            pclose(popen($command, 'r'));
        } else {
            return [
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => 'No se encontró Microsoft Edge en el servidor para realizar la impresión directa.'
                ]
            ];
        }

        dispatch(function () use ($tempPath) {
            sleep(15);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        })->afterResponse();

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Impresión directa enviada a: ' . $printerName
            ]
        ];
    }

    /**
     * Envía cotización o comprobante de venta por correo electrónico con PDF adjunto.
     */
    public function enviarCotizacionPorCorreo(Sale $sale): array
    {
        if (empty($sale->client->email)) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'El cliente asignado no tiene una dirección de correo electrónico registrada.'
                ]
            ];
        }

        $isQuote = $sale->document_type === 'quote';

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

        $pdf = Pdf::loadView('sales.pdf_sale', ['sale' => $sale, 'isEmail' => true]);
        $pdfRawData = $pdf->output();
        $pdfFileName = PdfHelper::formatFileName($sale->document_type, $sale->document_number, $sale->client, $sale->vehicle);

        Mail::to($sale->client->email)->send(
            new TestNotificationMail($data, $pdfRawData, $pdfFileName)
        );

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => $isQuote ? '¡Cotización enviada al correo del cliente con éxito!' : '¡Comprobante de venta enviado al correo del cliente con éxito!'
            ]
        ];
    }
}

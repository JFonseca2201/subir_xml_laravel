<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder\WorkOrder;
use App\Services\InvoiceStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceFileController extends Controller
{
    /**
     * Genera la factura en PDF y almacena los comprobantes de pago en la estructura jerárquica
     */
    public function storeAndGenerate(Request $request, int $orderId, InvoiceStorageService $storageService)
    {
        // Validar comprobantes opcionales (imágenes o PDF)
        $request->validate([
            'receipts' => 'nullable|array',
            'receipts.*' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:10240'
        ]);

        // 1. Obtener la orden con sus relaciones (cliente, items, pagos, etc.)
        $order = WorkOrder::with(['client'])->findOrFail($orderId);

        // Obtenemos el nombre del cliente (ajusta según tus campos: name, full_name, etc.)
        $clientName = $order->client ? ($order->client->name ?? $order->client->full_name ?? 'Cliente') : 'Sin_Nombre';

        // 2. Renderizar el PDF en memoria con DOMPDF
        // (Usa la vista blade que tengas para tu factura/orden)
        $pdf = Pdf::loadView('invoices.template', [
            'order' => $order,
            'client' => $order->client,
            'date' => Carbon::now()
        ]);
        $pdfBinary = $pdf->output();

        // 3. Capturar archivos de comprobantes enviados
        $receiptFiles = $request->file('receipts', []);

        // 4. Guardar jerárquicamente con el servicio
        $saved = $storageService->saveInvoiceAndReceipts(
            orderNumber: $order->id,
            clientName: $clientName,
            pdfBinaryContent: $pdfBinary,
            receiptFiles: $receiptFiles,
            date: Carbon::now()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Factura y comprobantes procesados y organizados correctamente.',
            'data' => $saved
        ], 200);
    }
}

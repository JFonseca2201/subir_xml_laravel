<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceXmlImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'xml' => 'required|file|mimes:xml,txt'
        ]);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($request->file('xml'));

        if (!$xml) {
            return response()->json([
                'message' => 'Invalid XML file'
            ], 422);
        }

        /**
         * XML AUTORIZADO:
         * <autorizacion>
         *   <estado>AUTORIZADO</estado>
         *   <comprobante><![CDATA[ ...factura... ]]></comprobante>
         * </autorizacion>
         */
        if (isset($xml->comprobante)) {
            $xml = simplexml_load_string($xml->comprobante);
        }

        DB::beginTransaction();

        try {
            // =========================
            // INFO TRIBUTARIA
            // =========================
            $infoTributaria = $xml->infoTributaria;

            $supplier = Supplier::firstOrCreate(
                ['tax_id' => (string) $infoTributaria->ruc],
                [
                    'name'    => (string) $infoTributaria->razonSocial,
                    'address' => (string) $infoTributaria->dirMatriz
                ]
            );

            // =========================
            // PREVENIR DUPLICADOS
            // =========================
            $accessKey = (string) $infoTributaria->claveAcceso;

            if (Invoice::where('access_key', $accessKey)->exists()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Invoice already imported'
                ], 409);
            }

            // =========================
            // FACTURA
            // =========================
            $infoFactura = $xml->infoFactura;

            $invoice = Invoice::create([
                'supplier_id'    => $supplier->id,
                'access_key'     => $accessKey,
                'invoice_number' => (string) $infoTributaria->estab . '-' .
                                    (string) $infoTributaria->ptoEmi . '-' .
                                    (string) $infoTributaria->secuencial,
                'issue_date'     => \Carbon\Carbon::createFromFormat(
                                        'd/m/Y',
                                        (string) $infoFactura->fechaEmision
                                    ),
                'subtotal'       => (float) $infoFactura->totalSinImpuestos,
                'tax'            => (float) $infoFactura->totalConImpuestos
                                        ->totalImpuesto->valor,
                'total'          => (float) $infoFactura->importeTotal
            ]);

            // =========================
            // ITEMS
            // =========================
            foreach ($xml->detalles->detalle as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'code'       => (string) $item->codigoPrincipal,
                    'description'=> (string) $item->descripcion,
                    'quantity'   => (float) $item->cantidad,
                    'unit_price'=> (float) $item->precioUnitario,
                    'discount'  => (float) $item->descuento,
                    'total'     => (float) $item->precioTotalSinImpuesto
                ]);
            }

            DB::commit();

            return response()->json([
                'message'    => 'Invoice imported successfully',
                'invoice_id' => $invoice->id
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error importing invoice',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
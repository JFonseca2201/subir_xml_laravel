<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoices\InvoiceCollection;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supplier;
// MODELS
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceXmlImportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $supplier = $request->supplier_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $invoices = Invoice::filterAdvance($search, $start_date, $end_date, $supplier)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'total_page' => $invoices->lastPage(),
            'invoices' => InvoiceCollection::make($invoices),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'xml' => 'required|file|mimes:xml',
            'item_type' => 'required',
        ]);

        DB::beginTransaction();

        try {

            /** -----------------------------
             * 1. READ XML FILE
             * ------------------------------*/
            $file = $request->file('xml');
            $xmlContent = file_get_contents($file->getRealPath());

            // Remove BOM if exists
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

            $xml = simplexml_load_string(
                $xmlContent,
                'SimpleXMLElement',
                LIBXML_NOCDATA
            );

            if ($xml === false) {
                throw new \Exception('Archivo XML no válido.');
            }

            /** -----------------------------
             * 2. HANDLE AUTORIZACION -> FACTURA
             * ------------------------------*/
            if (isset($xml->comprobante)) {
                $xml = simplexml_load_string(
                    (string) $xml->comprobante,
                    'SimpleXMLElement',
                    LIBXML_NOCDATA
                );
            }

            if ($xml === false || ! isset($xml->infoTributaria)) {
                throw new \Exception('XML no válido.');
            }

            /** -----------------------------
             * 3. ACCESS KEY (DUPLICATE CHECK)
             * ------------------------------*/
            $accessKey = (string) $xml->infoTributaria->claveAcceso;

            if (Invoice::where('access_key', $accessKey)->exists()) {
                return response()->json([
                    'status' => 409,
                    'message' => 'La factura ya a sido impiortada!',
                ], 409);
            }

            /** -----------------------------
             * 4. SUPPLIER
             * ------------------------------*/
            $supplier = Supplier::firstOrCreate(
                [
                    'tax_id' => (string) $xml->infoTributaria->ruc,
                ],
                [
                    'name' => (string) $xml->infoTributaria->razonSocial,
                    'ruc' => (string) $xml->infoTributaria->ruc,
                    'trade_name' => (string) $xml->infoTributaria->nombreComercial,
                    'address' => (string) $xml->infoTributaria->dirMatriz,
                ]
            );

            /** -----------------------------
             * 5. INVOICE
             * ------------------------------*/
            $issueDate = Carbon::createFromFormat(
                'd/m/Y',
                (string) $xml->infoFactura->fechaEmision
            );

            // IVA / impuestos
            $tax = 0;
            if (isset($xml->infoFactura->totalConImpuestos->totalImpuesto)) {
                foreach ($xml->infoFactura->totalConImpuestos->totalImpuesto as $taxItem) {
                    $tax += (float) $taxItem->valor;
                }
            }

            // Descuento global
            $globalDiscount = 0;
            if (isset($xml->infoFactura->totalDescuento)) {
                $globalDiscount = (float) $xml->infoFactura->totalDescuento;
            }

            $invoice = Invoice::create([
                'supplier_id' => $supplier->id,
                'access_key' => $accessKey,
                'invoice_number' => (string) $xml->infoTributaria->secuencial,
                'issue_date' => $issueDate,
                'subtotal' => (float) $xml->infoFactura->totalSinImpuestos,
                'discount' => $globalDiscount,
                'tax' => $tax,
                'total' => (float) $xml->infoFactura->importeTotal,
            ]);

            /** -----------------------------
             * 6. INVOICE ITEMS (PRODUCTS)
             * ------------------------------*/
            foreach ($xml->detalles->detalle as $item) {

                // Descuento por línea
                $lineDiscount = isset($item->descuento)
                    ? (float) $item->descuento
                    : 0;

                // Datos base
                $item_type = (int) $request->item_type;
                $quantity = (float) $item->cantidad;
                $unitPrice = (float) $item->precioUnitario;
                $subtotal = (float) $item->precioTotalSinImpuesto;

                // Impuestos por ítem
                $itemTax = 0;
                if (isset($item->impuestos->impuesto)) {
                    foreach ($item->impuestos->impuesto as $taxItem) {
                        $itemTax += (float) $taxItem->valor;
                    }
                }

                // Total final del ítem
                $total = round($subtotal - $lineDiscount + $itemTax, 2);

                $invoice_items = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'code' => (string) $item->codigoPrincipal,
                    'description' => (string) $item->descripcion,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,      // ✅ CLAVE para evitar error SQL
                    'discount' => $lineDiscount,
                    'tax' => $itemTax,
                    'total' => $total,
                    'item_type' => $item_type,
                ]);
            }

            DB::commit();

            /** -----------------------------
             * RESPONSE (NO TOCADO)
             * ------------------------------*/
            return response()->json([
                'status' => 200,
                'message' => 'Factura importada con éxito.',
                'data' => [
                    'id' => $invoice->id,
                    'supplier' => [
                        'id' => $invoice->supplier->id,
                        'name' => $invoice->supplier->name,
                        'trade_name' => $invoice->supplier->trade_name,
                        'address' => $invoice->supplier->address,
                        'tax_id' => $invoice->supplier->tax_id,
                    ],
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date' => $invoice->issue_date,
                    'subtotal' => $invoice->subtotal,
                    'disscount' => $invoice->disscount,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'invoice_items' => $invoice_items,
                ],
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error importing invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function config()
    {
        $suppliers = Supplier::orderBy('name', 'desc')->get();

        return response()->json([
            'suppliers' => $suppliers,
        ]);

    }

    public function show($id)
    {
        $invoice = Invoice::with(['supplier', 'invoices_items'])->findOrFail($id);

        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $invoice->id,
                'supplier' => [
                    'id' => $invoice->supplier->id,
                    'name' => $invoice->supplier->name,
                    'trade_name' => $invoice->supplier->trade_name,
                    'address' => $invoice->supplier->address,
                    'tax_id' => $invoice->supplier->tax_id,
                    'ruc' => $invoice->supplier->ruc,
                ],
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date,
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'invoice_items' => $invoice->invoices_items,
            ],
        ], 200);
    }

    public function updateType(Request $request, $id)
    {
        $request->validate([
            'item_type' => 'required|int|max:55',
        ]);
        $invoiceItem = InvoiceItem::find($id);
        if (! $invoiceItem) {
            return response()->json(['message' => 'Item de factura no encontrado'], 404);
        }
        $invoiceItem->item_type = (int) $request->input('item_type');
        $invoiceItem->save();

        return response()->json([
            'invoiceItem' => $invoiceItem,
            'status' => 200,
        ]);
    }
}

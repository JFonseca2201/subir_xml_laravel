<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoices\InvoiceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// MODELS
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;


class InvoiceXmlImportController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->get('search');
        $supplier=$request->supplier_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $type= $request->end_date;
        
        $invoices = Invoice::filterAdvance($search,  $start_date, $end_date, $type, $supplier)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'total_page' => $invoices->lastPage(),
            'invoices' => InvoiceCollection::make($invoices),
        ]);
    }


    public function store(Request $request)
    {
        Log::info('Item type recibido para items:', [$request->item_type]);
        Log::info('--- DEBUG START ---');
        Log::info('Todos los inputs del request:', $request->all());
        Log::info('Archivo recibido:', [$request->file('xml')]);
        Log::info('item_type recibido:', [$request->input('item_type')]);
        Log::info('--- DEBUG END ---');


        $request->validate([
            'xml' => 'required|file|mimes:xml',
            'item_type' => 'required'
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
                throw new \Exception('Archivo XML no valido.');
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

            if ($xml === false || !isset($xml->infoTributaria)) {
                throw new \Exception('XML no válido.');
            }

            /** -----------------------------
             * 3. ACCESS KEY (DUPLICATE CHECK)
             * ------------------------------*/
            $accessKey = (string) $xml->infoTributaria->claveAcceso;

            if (Invoice::where('access_key', $accessKey)->exists()) {
                return response()->json([
                    'status'=>409,
                    'message' => 'La factura ya a sido impiortada!'
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
                    'name'        => (string) $xml->infoTributaria->razonSocial,
                    'ruc' => (string) $xml->infoTributaria->ruc,
                    'trade_name'  => (string) $xml->infoTributaria->nombreComercial,
                    'address'     => (string) $xml->infoTributaria->dirMatriz,
                ]
            );

            /** -----------------------------
             * 5. INVOICE
             * ------------------------------*/
            $issueDate = Carbon::createFromFormat(
                'd/m/Y',
                (string) $xml->infoFactura->fechaEmision
            );

            $tax = 0;
            if (isset($xml->infoFactura->totalConImpuestos->totalImpuesto)) {
                foreach ($xml->infoFactura->totalConImpuestos->totalImpuesto as $taxItem) {
                    $tax += (float) $taxItem->valor;
                }
            }

            $invoice = Invoice::create([
                'supplier_id'    => $supplier->id,
                'access_key'     => $accessKey,
                'invoice_number' => (string) $xml->infoTributaria->secuencial,
                'issue_date'     => $issueDate,
                'subtotal'       => (float) $xml->infoFactura->totalSinImpuestos,
                'tax'            => $tax,
                'total'          => (float) $xml->infoFactura->importeTotal,
                
            ]);

            /** -----------------------------
             * 6. INVOICE ITEMS (PRODUCTS)
             * ------------------------------*/
             
                foreach ($xml->detalles->detalle as $item) {

                    // Obtener item_type desde el request
                    $itemTypeInput = $request->input('item_type');

                    // Validar y asignar solo valores válidos
                    $item_type = (int) $request->item_type;
                    $quantity  = (float) $item->cantidad;
                    $unitPrice = (float) $item->precioUnitario;
                    $subtotal  = (float) $item->precioTotalSinImpuesto;

                    $itemTax = 0;
                    if (isset($item->impuestos->impuesto)) {
                        foreach ($item->impuestos->impuesto as $taxItem) {
                            $itemTax += (float) $taxItem->valor;
                        }
                    }

                    $total = round($subtotal + $itemTax, 2);

                 $invoice_items=   InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'code'        => (string) $item->codigoPrincipal,
                        'description' => (string) $item->descripcion,
                        'quantity'    => $quantity,
                        'unit_price'  => $unitPrice,
                        'subtotal'    => $subtotal,
                        'tax'         => $itemTax,
                        'total'       => $total,
                        'item_type'   => $item_type, // ya es int seguro
                    ]);
                }

                

            DB::commit();

            //$invoice->load('supplier');

            return response()->json([
                'status'  => 200,
                'message' => 'Factura importada con éxito.',
                'data'    => [
                    'id'             => $invoice->id,
                    'supplier'       => [
                        'id'   => $invoice->supplier->id,
                        'name' => $invoice->supplier->name,
                        'trade_name' => $invoice->supplier->trade_name,
                        'address' => $invoice->supplier->address,
                        'tax_id' => $invoice->supplier->tax_id,
                    ],
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date'     => $invoice->issue_date,
                    'subtotal'       => $invoice->subtotal,
                    'tax'            => $invoice->tax,
                    'total'          => $invoice->total,
                    "invoice_items"=>$invoice_items,                   
                ],
                
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error importing invoice',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function config(){
        $suppliers=Supplier::orderBy('name', 'desc')->get();
        return response()->json([
            'suppliers'=>$suppliers
        ]);

    }
}
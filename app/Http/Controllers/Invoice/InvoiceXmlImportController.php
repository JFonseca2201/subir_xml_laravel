<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoices\InvoiceCollection;
use App\Models\Config\ProductCategorie;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product\Product;
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
        $page = $request->get('page', 1);
        $per_page = $request->get('per_page', 10);

        $invoices = Invoice::filterAdvance($search, $start_date, $end_date, $supplier)
            ->orderBy('id', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);

        return response()->json([
            'total' => $invoices->total(),
            'count' => $invoices->count(),
            'per_page' => $invoices->perPage(),
            'current_page' => $invoices->currentPage(),
            'total_pages' => $invoices->lastPage(),
            'from' => $invoices->firstItem(),
            'to' => $invoices->lastItem(),
            'has_more_pages' => $invoices->hasMorePages(),
            'next_page_url' => $invoices->nextPageUrl(),
            'prev_page_url' => $invoices->previousPageUrl(),
            'first_page_url' => $invoices->url(1),
            'last_page_url' => $invoices->url($invoices->lastPage()),
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

            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                throw new \Exception('Archivo XML no válido.');
            }

            /** -----------------------------
             * 2. HANDLE AUTORIZACION -> FACTURA
             * ------------------------------*/
            if (isset($xml->comprobante)) {
                $xml = simplexml_load_string((string) $xml->comprobante, 'SimpleXMLElement', LIBXML_NOCDATA);
            }

            if ($xml === false || !isset($xml->infoTributaria)) {
                throw new \Exception('XML no válido.');
            }

            /** -----------------------------
             * 3. ACCESS KEY (DUPLICATE CHECK)
             * ------------------------------*/
            $accessKey = (string) $xml->infoTributaria->claveAcceso;

            // 1. VALIDACIÓN CRÍTICA: ¿Ya existe esta factura?
            $facturaExiste = Invoice::where('access_key', $accessKey)->exists();

            if ($facturaExiste) {
                return response()->json([
                    'status' => 422,
                    'message' => 'La factura con clave de acceso ' . $accessKey . ' ya fue ingresada anteriormente. El stock no ha sido modificado.',
                    'access_key' => $accessKey,
                    'error_type' => 'duplicate_invoice'
                ], 422); // Error de validación
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
                ],
            );

            /** -----------------------------
             * 5. INVOICE
             * ------------------------------*/
            $issueDate = Carbon::createFromFormat('d/m/Y', (string) $xml->infoFactura->fechaEmision);

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
                'invoice_process' => 2, // 2 = no procesado por defecto
            ]);

            /** -----------------------------
             * 6. INVOICE ITEMS (PRODUCTS)
             * ------------------------------*/
            foreach ($xml->detalles->detalle as $item) {
                // Descuento por línea
                $lineDiscount = isset($item->descuento) ? (float) $item->descuento : 0;

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

                /** -----------------------------
                 * 6.1 VERIFICAR Y CREAR/ACTUALIZAR PRODUCTO
                 * ------------------------------*/
                $code = (string) $item->codigoPrincipal;
                $description = (string) $item->descripcion;

                // Solo procesar productos si item_type = 1


                $invoice_items = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'code' => $code,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal, // ✅ CLAVE para evitar error SQL
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
            return response()->json(
                [
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
                ],
                201,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                [
                    'message' => 'Error importing invoice',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function config()
    {
        $suppliers = Supplier::orderBy('name', 'desc')->get();
        $category = ProductCategorie::orderBy('title', 'desc')->get();

        return response()->json([
            'status' => 200,
            'suppliers' => $suppliers,
            'categories' => $category,
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['supplier', 'invoice_items'])->findOrFail($id);

        return response()->json(
            [
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
                    'invoice_items' => $invoice->invoice_items,
                ],
            ],
            200,
        );
    }

    public function updateType(Request $request, $id)
    {
        $request->validate([
            'item_type' => 'required|int|max:55',
            'categorie_id' => 'nullable|int|exists:product_categories,id',
        ]);
        $invoiceItem = InvoiceItem::find($id);
        if (!$invoiceItem) {
            return response()->json(['message' => 'Item de factura no encontrado'], 404);
        }
        $invoiceItem->item_type = (int) $request->input('item_type');

        // Actualizar categoría si se proporciona
        if ($request->has('categorie_id')) {
            $invoiceItem->categorie_id = $request->input('categorie_id');
        }

        $invoiceItem->save();

        return response()->json([
            'invoiceItem' => $invoiceItem->fresh(['category']),
            'status' => 200,
        ]);
    }

    public function processInvoice(Request $request)
    {
        try {
            // Validar datos requeridos
            $validated = $request->validate([
                'invoice' => 'required|integer|exists:invoices,id',
                'categorie_id' => 'nullable|int|exists:product_categories,id',
            ]);

            // Obtener la factura actual
            $invoiceModel = Invoice::find($validated['invoice']);
            if (!$invoiceModel) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            // Obtener todos los ítems de la factura
            $invoiceItems = InvoiceItem::where('invoice_id', $invoiceModel->id)->get();

            if ($invoiceItems->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'No se encontraron ítems en esta factura',
                ], 404);
            }

            $processedCount = 0;

            // Procesar cada ítem de la factura
            foreach ($invoiceItems as $invoiceItem) {
                // Usar la categoría del request si se proporciona, sino la del ítem
                $category = $validated['categorie_id'] ?? $invoiceItem->categorie_id;
                $category = $category ?? 1; // Default a 1 si es null

                $item_type = $invoiceItem->item_type;
                $code = $invoiceItem->code;
                $description = $invoiceItem->description;
                $quantity = $invoiceItem->quantity;
                $unitPrice = $invoiceItem->unit_price;

                if ($item_type == 1) {
                    // Buscar producto por SKU o descripción
                    $product = Product::where('sku', $code)
                        ->orWhere('description', $description)
                        ->first();

                    if (!$product) {
                        // Crear nuevo producto con valores quemados
                        $product = Product::create([
                            'description' => $description,
                            'sku' => $code,
                            'imagen' => null,
                            'code_aux' => '',
                            'uses' => null,
                            'product_categorie_id' => $category,
                            'warehouse_id' => 1,
                            'unit_id' => 1,
                            'supplier_id' => $invoiceModel->supplier_id,
                            'price' => $unitPrice * 1.55,
                            'price_sale' => $unitPrice * 1.55,
                            'purchase_price' => $unitPrice,
                            'tax_rate' => 15,
                            'max_discount' => (float) ((($unitPrice * 1.55) - ($unitPrice)) * 0.25),
                            'discount_percentage' => 25,
                            'brand' => 'SM',
                            'stock' => $quantity,
                            'item_type' => $item_type,
                            'min_stock' => 1,
                            'max_stock' => 5,
                            'is_taxable' => 1,
                            'is_gift' => 2,
                            'notes' => 'Producto importado desde la Factura: ' . $invoiceModel->invoice_number,
                            'state' => 1,
                        ]);
                        $processedCount++;
                    } else {
                        // Actualizar stock del producto existente
                        $product->stock += $quantity;
                        $product->save();
                        $processedCount++;
                    }
                }
            }

            // Actualizar el estado de la factura (1 = procesada)
            $invoiceModel->update([
                'invoice_process' => 1
            ]);

            return response()->json([
                'message' => $processedCount . ' producto(s) procesado(s) correctamente',
                'status' => 200,
                'invoice' => $invoiceModel->fresh(['supplier']),
                'processed_items' => $processedCount,
                'total_items' => $invoiceItems->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al procesar la factura',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoices\InvoiceCollection;
use App\Models\Config\ProductCategorie;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\Product\Product;
use App\Models\Supplier\Supplier;
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
            'xml' => 'required|file',
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
        $invoice = Invoice::with(['supplier', 'invoice_items', 'financeRecords.paymentDistributions.account', 'accountPayable'])->findOrFail($id);

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
                    'invoice_process' => $invoice->invoice_process,
                    'issue_date' => $invoice->issue_date,
                    'subtotal' => $invoice->subtotal,
                    'discount' => $invoice->discount,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'invoice_items' => $invoice->invoice_items,
                    'finance_records' => $invoice->financeRecords,
                    'account_payable' => $invoice->accountPayable,
                ],
            ],
            200,
        );
    }

    public function updateType(Request $request, $id)
    {
        $request->validate([
            'item_type' => 'required|int|max:55',
            'product_categorie_id' => 'nullable|int|exists:product_categories,id',
        ]);
        $invoiceItem = InvoiceItem::find($id);
        if (!$invoiceItem) {
            return response()->json(['message' => 'Item de factura no encontrado'], 404);
        }
        $invoiceItem->item_type = (int) $request->input('item_type');

        // Actualizar categoría si se proporciona
        if ($request->has('product_categorie_id')) {
            $invoiceItem->product_categorie_id = $request->input('product_categorie_id');
        }

        $invoiceItem->save();

        return response()->json([
            'invoiceItem' => $invoiceItem->fresh(['category']),
            'status' => 200,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'access_key' => 'nullable|string',
            'invoice_number' => 'required|string',
            'issue_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'invoice_process' => 'nullable|integer',
            'customer_id' => 'nullable|exists:users,id',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        $invoice->update($request->all());

        return response()->json([
            'invoice' => $invoice->fresh(['supplier', 'customer', 'sucursal']),
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
                'payment_type' => 'required|string|in:efectivo,credito,aporte',
                'account_id' => 'nullable|integer|exists:accounts,id',
                'partner_id' => 'nullable|integer|exists:partners,id',
                'payment_distributions' => 'nullable|array',
                'payment_distributions.*.account_id' => 'required|integer|exists:accounts,id',
                'payment_distributions.*.amount' => 'required|numeric|min:0.01',
                'payment_distributions.*.payment_method' => 'required|string',
            ]);

            // Obtener la factura actual
            $invoiceModel = Invoice::with('supplier')->find($validated['invoice']);
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

            // Validar que todos los ítems de tipo producto físico (1) tengan categoría asignada
            foreach ($invoiceItems as $invoiceItem) {
                if ($invoiceItem->item_type == 1 && empty($invoiceItem->product_categorie_id)) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'No se puede procesar la factura porque hay productos físicos sin categoría asignada. Por favor, asigne categorías en el detalle de la factura antes de procesarla.',
                    ], 422);
                }
            }

            $processedCount = 0;

            // Procesar cada ítem de la factura
            foreach ($invoiceItems as $invoiceItem) {
                // Usar la categoría del request si se proporciona, sino la del ítem
                $category = $validated['categorie_id'] ?? $invoiceItem->product_categorie_id;

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

            // --- REGISTRAR MOVIMIENTO FINANCIERO / CUENTA POR PAGAR ---
            // Solo si no existe ya un registro para evitar duplicados en la parte financiera y cuentas por pagar
            $exists = \App\Models\Finance\FinanceRecord::where('invoice_number', $invoiceModel->invoice_number)->exists();
            $payableExists = \App\Models\Finance\AccountPayable::where('invoice_id', $invoiceModel->id)->exists();

            if (!$exists && !$payableExists) {
                if ($validated['payment_type'] === 'efectivo') {
                    $hasDistributions = !empty($validated['payment_distributions']);

                    if ($hasDistributions) {
                        $totalDist = array_sum(array_column($validated['payment_distributions'], 'amount'));
                        if (abs($totalDist - $invoiceModel->total) > 0.01) {
                            return response()->json([
                                'status' => 422,
                                'message' => 'La suma de los montos de pago ($' . number_format($totalDist, 2) . ') debe coincidir con el total de la factura ($' . number_format($invoiceModel->total, 2) . ').',
                            ], 422);
                        }
                    } else {
                        if (empty($validated['account_id'])) {
                            return response()->json([
                                'status' => 422,
                                'message' => 'Se requiere seleccionar al menos una cuenta para registrar el pago.',
                            ], 422);
                        }
                    }

                    $supplierName = $invoiceModel->supplier ? ($invoiceModel->supplier->trade_name ?? $invoiceModel->supplier->name) : ('#' . $invoiceModel->supplier_id);
                    $financeRecord = new \App\Models\Finance\FinanceRecord([
                        'type' => \App\Models\Finance\FinanceRecord::TYPE_EXPENSE, // 1 = Egreso
                        'amount' => $invoiceModel->total,
                        'invoice_number' => $invoiceModel->invoice_number,
                        'description' => 'Pago por Compra XML a Proveedor ' . $supplierName,
                        'user_id' => auth()->id() ?? 1,
                        'entry_date' => \Carbon\Carbon::now('America/Guayaquil')->toDateString()
                    ]);

                    if (!$hasDistributions) {
                        $financeRecord->account_id = $validated['account_id'];
                    } else {
                        $financeRecord->account_id = $validated['payment_distributions'][0]['account_id'];
                    }

                    $financeRecord->save();

                    if ($hasDistributions) {
                        foreach ($validated['payment_distributions'] as $dist) {
                            $distribution = \App\Models\Finance\PaymentDistribution::create([
                                'finance_record_id' => $financeRecord->id,
                                'account_id' => $dist['account_id'],
                                'amount' => $dist['amount'],
                                'payment_method' => $dist['payment_method'],
                            ]);

                            $distribution->registerMovement(
                                $dist['account_id'],
                                'expense',
                                $dist['amount'],
                                $financeRecord->description . ' - ' . ($dist['payment_method'] === 'cash' ? 'Efectivo' : 'Transferencia'),
                                $financeRecord->entry_date instanceof \Carbon\Carbon ? $financeRecord->entry_date->toDateString() : $financeRecord->entry_date,
                                [
                                    'finance_record_id' => $financeRecord->id,
                                    'record_type' => 1,
                                    'invoice' => $financeRecord->invoice_number,
                                ]
                            );

                            $account = \App\Models\Finance\Account::find($dist['account_id']);
                            if ($account) {
                                $account->updateBalance($dist['amount'], 1); // 1 = Egreso
                            }
                        }
                    } else {
                        $accountId = $validated['account_id'];
                        $distribution = \App\Models\Finance\PaymentDistribution::create([
                            'finance_record_id' => $financeRecord->id,
                            'account_id' => $accountId,
                            'amount' => $invoiceModel->total,
                            'payment_method' => $financeRecord->payment_method ?? 'cash',
                        ]);

                        $distribution->registerMovement(
                            $accountId,
                            'expense',
                            $invoiceModel->total,
                            $financeRecord->description,
                            $financeRecord->entry_date instanceof \Carbon\Carbon ? $financeRecord->entry_date->toDateString() : $financeRecord->entry_date,
                            [
                                'finance_record_id' => $financeRecord->id,
                                'record_type' => 1,
                                'invoice' => $financeRecord->invoice_number,
                            ]
                        );

                        $account = \App\Models\Finance\Account::find($accountId);
                        if ($account) {
                            $account->updateBalance($invoiceModel->total, 1);
                        }
                    }

                } else if ($validated['payment_type'] === 'aporte') {
                    if (empty($validated['partner_id'])) {
                        return response()->json([
                            'status' => 422,
                            'message' => 'Se requiere seleccionar un socio para el pago con aporte.',
                        ], 422);
                    }

                    // Buscar la cuenta ligada a los aportes de capital del socio
                    $aporte = \App\Models\Partner\AporteCapital::where('partner_id', $validated['partner_id'])->latest()->first();
                    if (!$aporte || !$aporte->cuenta_id) {
                        return response()->json([
                            'status' => 422,
                            'message' => 'El socio seleccionado no tiene aportes de capital ni cuenta asociada.',
                        ], 422);
                    }
                    
                    $accountId = $aporte->cuenta_id;

                    $supplierName = $invoiceModel->supplier ? ($invoiceModel->supplier->trade_name ?? $invoiceModel->supplier->name) : ('#' . $invoiceModel->supplier_id);
                    $financeRecord = new \App\Models\Finance\FinanceRecord([
                        'type' => \App\Models\Finance\FinanceRecord::TYPE_EXPENSE, // 1 = Egreso
                        'amount' => $invoiceModel->total,
                        'invoice_number' => $invoiceModel->invoice_number,
                        'description' => 'Pago por Compra XML a Proveedor ' . $supplierName . ' (Financiado por Aporte de Socio)',
                        'user_id' => auth()->id() ?? 1,
                        'account_id' => $accountId,
                        'entry_date' => \Carbon\Carbon::now('America/Guayaquil')->toDateString()
                    ]);

                    $financeRecord->save();

                    $distribution = \App\Models\Finance\PaymentDistribution::create([
                        'finance_record_id' => $financeRecord->id,
                        'account_id' => $accountId,
                        'amount' => $invoiceModel->total,
                        'payment_method' => $financeRecord->payment_method ?? 'cash',
                    ]);

                    $distribution->registerMovement(
                        $accountId,
                        'expense',
                        $invoiceModel->total,
                        $financeRecord->description,
                        $financeRecord->entry_date instanceof \Carbon\Carbon ? $financeRecord->entry_date->toDateString() : $financeRecord->entry_date,
                        [
                            'finance_record_id' => $financeRecord->id,
                            'record_type' => 1,
                            'invoice' => $financeRecord->invoice_number,
                        ]
                    );

                    $account = \App\Models\Finance\Account::find($accountId);
                    if ($account) {
                        $account->updateBalance($invoiceModel->total, 1);
                    }

                } else if ($validated['payment_type'] === 'credito') {
                    \App\Models\Finance\AccountPayable::create([
                        'supplier_id' => $invoiceModel->supplier_id,
                        'invoice_id' => $invoiceModel->id,
                        'total_amount' => $invoiceModel->total,
                        'amount_paid' => 0,
                        'status' => 'pending',
                        'due_date' => \Carbon\Carbon::now()->addDays(30), // Por defecto 30 días
                    ]);
                }
            }

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
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            try {
                $invoice = Invoice::findOrFail($id);

                // 1. Revertir inventario si la factura fue procesada
                if ($invoice->invoice_process === 1) {
                    $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
                    foreach ($invoiceItems as $item) {
                        if ((int) $item->item_type === 1) { // 1 = Producto físico
                            $product = Product::where('sku', $item->code)
                                ->orWhere('description', $item->description)
                                ->first();

                            if ($product) {
                                // Revertir stock
                                $product->stock = max(0, $product->stock - $item->quantity);
                                $product->save();
                            }
                        }
                    }
                }

                // 2. Revertir Cuentas por Pagar (Compras a Crédito)
                $accountPayable = \App\Models\Finance\AccountPayable::where('invoice_id', $invoice->id)->first();
                if ($accountPayable) {
                    if ((float) $accountPayable->amount_paid > 0) {
                        return response()->json([
                            'status' => 400,
                            'message' => 'No se puede eliminar la factura porque ya existen abonos o pagos asociados a la cuenta por pagar.'
                        ], 400);
                    }
                    $accountPayable->delete();
                }

                // 3. Revertir Movimientos Financieros (Pagos al Contado/Aportes)
                if ($invoice->invoice_number) {
                    $financeRecords = \App\Models\Finance\FinanceRecord::where('invoice_number', $invoice->invoice_number)->get();
                    foreach ($financeRecords as $financeRecord) {
                        $paymentDistributions = $financeRecord->paymentDistributions;
                        if ($paymentDistributions && $paymentDistributions->count() > 0) {
                            foreach ($paymentDistributions as $distribution) {
                                // Eliminar el movimiento financiero asociado
                                if (method_exists($distribution, 'financialMovement')) {
                                    $distribution->financialMovement()->delete();
                                }

                                // Revertir saldo en la cuenta bancaria / efectivo
                                $account = \App\Models\Finance\Account::find($distribution->account_id);
                                if ($account) {
                                    // Como fue un egreso (type = 1), devolvemos el dinero usando type=0 (Ingreso)
                                    $account->updateBalance($distribution->amount, 0);
                                }
                            }
                        } else {
                            // Lógica de respaldo
                            if (method_exists($financeRecord, 'financialMovement')) {
                                $financeRecord->financialMovement()->delete();
                            }
                            $account = \App\Models\Finance\Account::find($financeRecord->account_id);
                            if ($account) {
                                $account->updateBalance($financeRecord->amount, 0);
                            }
                        }
                        // Borrar el registro financiero
                        $financeRecord->delete();
                    }
                }

                // 4. Borrar items y la factura
                InvoiceItem::where('invoice_id', $invoice->id)->delete();
                $invoice->delete();

                return response()->json([
                    'status' => 200,
                    'message' => 'Factura y todos sus registros asociados eliminados correctamente.',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 500,
                    'message' => 'Error al eliminar la factura.',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
    }
}

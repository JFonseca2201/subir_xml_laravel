<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\ExpenseDetail;
use App\Models\Product\Product;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\AccountPayable;
use App\Models\Partner\AporteCapital;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PurchaseManualController extends Controller
{
    public function store(Request $request)
    {
        $purchaseType = $request->input('purchase_type', 'products'); // 'products' | 'supplies' | 'expenses'
        $isExpense = $request->boolean('is_expense') || in_array($purchaseType, ['supplies', 'expenses']);

        $rules = [
            'supplier_id' => 'nullable',
            'supplier_ruc' => 'nullable|string|max:20',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_address' => 'nullable|string|max:500',
            'invoice_number' => 'nullable|string|max:100',
            'document_type' => 'nullable|string|max:50',
            'issue_date' => 'required',
            'payment_type' => 'required|string|in:efectivo,credito,aporte',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'access_key' => 'nullable',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'is_expense' => 'nullable|boolean',
            'purchase_type' => 'nullable|string|in:products,supplies,expenses',
            'is_shared' => 'nullable|boolean',
            'terceros_total' => 'nullable|numeric|min:0',
            'taller_total' => 'nullable|numeric|min:0',
        ];

        if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
            $rules = array_merge($rules, [
                'items' => 'required|array|min:1',
                'items.*.code' => 'nullable',
                'items.*.description' => 'required',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.subtotal' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'items.*.tax' => 'nullable|numeric|min:0',
                'items.*.total' => 'required|numeric|min:0',
                'items.*.item_type' => 'required',
                'items.*.usage_type' => 'nullable|string',
                'items.*.product_categorie_id' => 'nullable',
                'items.*.brand' => 'nullable',
            ]);
        } elseif ($isExpense && $request->has('expense_details')) {
            $rules = array_merge($rules, [
                'expense_details' => 'required|array|min:1',
                'expense_details.*.description' => 'required|string|max:255',
                'expense_details.*.category' => 'nullable|string|max:255',
                'expense_details.*.total' => 'required|numeric|min:0.01',
            ]);
        } else {
            $rules = array_merge($rules, [
                'items' => 'required|array|min:1',
                'items.*.code' => 'nullable',
                'items.*.description' => 'required',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.subtotal' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'items.*.tax' => 'nullable|numeric|min:0',
                'items.*.total' => 'required|numeric|min:0',
                'items.*.item_type' => 'required',
                'items.*.usage_type' => 'nullable|string',
                'items.*.product_categorie_id' => 'nullable',
                'items.*.brand' => 'nullable',
            ]);
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {
            // Resolver tipo de documento
            $rawDocType = strtolower(trim((string) $request->input('document_type', 'invoice')));
            if (in_array($rawDocType, ['sale_note', 'nota_venta', 'nota de venta'])) {
                $documentType = Invoice::DOC_SALE_NOTE;
            } elseif (in_array($rawDocType, ['internal', 'sin_documento', 'comprobante interno', 'comprobante_interno', 'sin documento'])) {
                $documentType = Invoice::DOC_INTERNAL;
            } else {
                $documentType = Invoice::DOC_INVOICE;
            }

            // Resolver número de comprobante (autogenerar INT-XXXX si es interno o si no se especificó)
            $invoiceNumber = trim((string) $request->input('invoice_number', ''));
            if ($documentType === Invoice::DOC_INTERNAL || empty($invoiceNumber)) {
                $lastInternal = Invoice::where('invoice_number', 'LIKE', 'INT-%')->orderByDesc('id')->first();
                $nextNum = 1;
                if ($lastInternal && preg_match('/INT-(\d+)/i', $lastInternal->invoice_number, $matches)) {
                    $nextNum = ((int)$matches[1]) + 1;
                }
                $invoiceNumber = 'INT-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            }

            // Resolver Proveedor (Existente por ID o por RUC, o crear nuevo)
            $supplierId = null;
            if ($request->supplier_id && is_numeric($request->supplier_id)) {
                $existing = \App\Models\Supplier\Supplier::find($request->supplier_id);
                if ($existing) {
                    $supplierId = $existing->id;
                }
            }

            if (!$supplierId) {
                $rawRuc = trim((string)($request->supplier_ruc ?: ($request->supplier_id ?: '9999999999001')));
                if (strlen($rawRuc) === 12 && !str_starts_with($rawRuc, '0')) {
                    $rawRuc = '0' . $rawRuc;
                }
                $rawName = strtoupper(trim((string)($request->supplier_name ?: 'PROVEEDOR ' . $rawRuc)));
                $rawAddress = strtoupper(trim((string)($request->supplier_address ?: 'S/N')));

                // Buscar proveedor existente por RUC (con o sin 0) o por nombre
                $existing = \App\Models\Supplier\Supplier::where('ruc', $rawRuc)
                    ->orWhere('tax_id', $rawRuc)
                    ->orWhere('ruc', ltrim($rawRuc, '0'))
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($rawName)])
                    ->first();

                if ($existing) {
                    $supplierId = $existing->id;
                } else {
                    $supplier = \App\Models\Supplier\Supplier::create([
                        'tax_id' => $rawRuc,
                        'ruc' => $rawRuc,
                        'name' => $rawName,
                        'address' => $rawAddress,
                    ]);
                    $supplierId = $supplier->id;
                }
            }

            // 1. Validar duplicados de Comprobante (Clave de Acceso y Número de Documento)
            if (!empty($request->access_key)) {
                $duplicateAccess = Invoice::where('access_key', $request->access_key)->first();
                if ($duplicateAccess) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Este comprobante ya fue ingresado anteriormente en el sistema (Clave de Acceso ya registrada).',
                    ], 422);
                }
            }

            // Solo validar número duplicado si no es comprobante interno y tiene proveedor asignado
            if ($documentType !== Invoice::DOC_INTERNAL && !empty($invoiceNumber) && $supplierId) {
                $duplicateNumber = Invoice::where('invoice_number', $invoiceNumber)
                    ->where('supplier_id', $supplierId)
                    ->where('document_type', $documentType)
                    ->first();
                if ($duplicateNumber) {
                    $docLabel = $documentType === Invoice::DOC_SALE_NOTE ? 'La Nota de Venta' : 'La Factura';
                    return response()->json([
                        'status' => 422,
                        'message' => "{$docLabel} N° {$invoiceNumber} ya existe registrada para este proveedor.",
                    ], 422);
                }
            }

            // Cálculos e impuestos (Si es Nota de Venta, no hay IVA)
            $tax = (float) ($request->tax ?? 0);
            $subtotal = (float) $request->subtotal;
            $total = (float) $request->total;

            if ($documentType === Invoice::DOC_SALE_NOTE) {
                $tax = 0.0;
                if ($total > 0 && $subtotal <= 0) {
                    $subtotal = $total;
                } elseif ($subtotal > 0 && $total <= 0) {
                    $total = $subtotal;
                } else {
                    $subtotal = $total;
                }
            }

            // 2. Create the Invoice (Purchase)
            $accessKey = $request->access_key ?: ('MANUAL-' . strtoupper(Str::random(10)) . '-' . time());
            $issueDate = Carbon::parse($request->issue_date);

            $invoice = Invoice::create([
                'supplier_id' => $supplierId,
                'document_type' => $documentType,
                'access_key' => $accessKey,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $issueDate,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => $tax,
                'total' => $total,
                'invoice_process' => 1, // Ya procesado (inventario o gasto general)
                'is_expense' => $isExpense,
                'created_by' => auth('api')->id() ?? auth()->id() ?? \App\Models\User::first()?->id ?? 1,
            ]);

            // 3. Guardar detalles según el tipo de compra (Mercadería Comercial vs Insumos Taller vs Gasto General)
            $breakdown = [
                'hasCommercialProduct' => false,
                'commercialTotal' => 0.0,
                'workshopTotal' => 0.0,
            ];

            if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
                $breakdown = $this->storeInventoryItems(
                    $invoice,
                    $request->items,
                    $supplierId,
                    (string) $invoiceNumber,
                    $documentType
                );
            } elseif ($request->has('expense_details')) {
                $this->storeExpenseDetails($invoice, $request->expense_details);
                $breakdown['workshopTotal'] = (float) $total;
            }

            $hasProducts = $breakdown['hasCommercialProduct'];

            // 4. Financial Integration
            $expenseAmount = (!$hasProducts || !$request->is_shared)
                ? (float) $total
                : (float) ($request->taller_total ?? ($total - ($request->terceros_total ?? 0)));

            if ($request->payment_type === 'efectivo' || $request->payment_type === 'aporte') {
                $supplier = \App\Models\Supplier\Supplier::find($supplierId);
                $supplierName = $supplier ? ($supplier->trade_name ?? $supplier->name) : ('#' . $supplierId);

                $docLabel = match($documentType) {
                    Invoice::DOC_SALE_NOTE => 'Nota de Venta',
                    Invoice::DOC_INTERNAL => 'Comprobante Interno',
                    default => 'Factura',
                };

                if (!$hasProducts) {
                    $typeTitle = $purchaseType === 'supplies' ? 'Insumos de Taller' : 'Gasto General';
                    $desc = 'Pago por ' . $typeTitle . ' (' . $docLabel . ') a Proveedor ' . $supplierName;
                } else {
                    $desc = 'Pago por Compra (' . $docLabel . ') a Proveedor ' . $supplierName;
                    if ($breakdown['commercialTotal'] > 0 && $breakdown['workshopTotal'] > 0) {
                        $desc .= ' [Mercadería Comercial: $' . number_format($breakdown['commercialTotal'], 2) . ' | Insumos Taller: $' . number_format($breakdown['workshopTotal'], 2) . ']';
                    } elseif ($breakdown['commercialTotal'] > 0) {
                        $desc .= ' [Mercadería Comercial: $' . number_format($breakdown['commercialTotal'], 2) . ']';
                    }
                    if ($request->is_shared) {
                        $desc .= ' [Comprobante Compartido: Taller $' . number_format($expenseAmount, 2) . ' / Total $' . number_format($total, 2) . ']';
                    }
                    $desc .= ' - Costo Operativo y Ventas';
                }

                $accountId = null;
                if ($request->payment_type === 'efectivo') {
                    if (!$request->account_id) {
                        throw new \Exception('Se requiere cuenta para pago en efectivo.');
                    }
                    $accountId = $request->account_id;
                } else if ($request->payment_type === 'aporte') {
                    if (!$request->partner_id) {
                        throw new \Exception('Se requiere seleccionar un socio para el pago con aporte.');
                    }

                    // Buscar la cuenta ligada a los aportes de capital del socio
                    $aporte = AporteCapital::where('partner_id', $request->partner_id)->latest()->first();
                    $accountId = $aporte?->cuenta_id ?? \App\Models\Finance\Account::first()?->id ?? 1;
                    $desc .= ' (Financiado por Aporte de Socio)';
                }

                $financeRecord = new FinanceRecord([
                    'type' => FinanceRecord::TYPE_EXPENSE,
                    'account_id' => $accountId,
                    'amount' => $expenseAmount,
                    'invoice_number' => $invoiceNumber,
                    'description' => $desc,
                    'user_id' => auth('api')->id() ?? auth()->id() ?? \App\Models\User::first()?->id ?? 1,
                    'entry_date' => $issueDate->toDateString(),
                ]);

                $financeRecord->save();

                // Registrar distribución de pagos para que funcione getCurrentBalanceAttribute
                $distribution = PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => $expenseAmount,
                    'payment_method' => $financeRecord->payment_method ?? 'cash',
                ]);

                // Registrar movimiento financiero para el dashboard y actualizar saldo de la cuenta
                $distribution->registerMovement(
                    $accountId,
                    'expense',
                    $expenseAmount,
                    $financeRecord->description,
                    $issueDate->toDateString(),
                    [
                        'finance_record_id' => $financeRecord->id,
                        'record_type' => 1, // expense
                        'invoice' => $financeRecord->invoice_number,
                    ]
                );

                $account = \App\Models\Finance\Account::find($accountId);
                if ($account) {
                    $account->updateBalance($expenseAmount, 1); // 1 = Egreso
                }

            } else if ($request->payment_type === 'credito') {
                AccountPayable::create([
                    'supplier_id' => $supplierId,
                    'invoice_id' => $invoice->id,
                    'total_amount' => $expenseAmount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'due_date' => Carbon::now()->addDays(30), // Por defecto 30 días
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => $isExpense ? 'Gasto general registrado con éxito' : 'Compra registrada con éxito',
                'data' => $invoice->load('invoice_items', 'expenseDetails', 'supplier'),
                'breakdown' => [
                    'commercial_inventory' => round($breakdown['commercialTotal'], 2),
                    'workshop_expenses' => round($breakdown['workshopTotal'], 2),
                    'total' => round((float) $invoice->total, 2),
                ],
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('Error al guardar compra manual: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar la compra: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Guarda las líneas de gasto general sin consultar ni afectar el inventario de productos.
     */
    private function storeExpenseDetails(Invoice $invoice, array $expenseDetails): void
    {
        foreach ($expenseDetails as $detail) {
            $category = trim((string) ($detail['category'] ?? ''));

            ExpenseDetail::create([
                'invoice_id' => $invoice->id,
                'description' => trim((string) $detail['description']),
                'category' => $category !== '' ? $category : 'General',
                'total' => round((float) $detail['total'], 2),
            ]);
        }
    }

    /**
     * Guarda los ítems de la factura (Productos Comerciales, Insumos Directos o Insumos por Envase).
     *
     * @return array Resumen de separación entre mercadería comercial e insumos/gastos operativos
     */
    private function storeInventoryItems(Invoice $invoice, array $items, int $supplierId, string $invoiceNumber, string $documentType = Invoice::DOC_INVOICE): array
    {
        $defaultWarehouseId = \App\Models\Config\Warehouse::first()?->id ?? 1;
        $defaultUnitId = \App\Models\Config\Unit::first()?->id ?? 1;
        $defaultCatId = \App\Models\Config\ProductCategorie::first()?->id ?? 1;
        $defaultTaxRate = (float) config('app.default_tax_rate', 15.00);

        $hasCommercialProduct = false;
        $commercialTotal = 0.0;
        $workshopTotal = 0.0;

        foreach ($items as $item) {
            $rawItemType = (int) ($item['item_type'] ?? 1);
            $rawUsageType = trim((string) ($item['usage_type'] ?? ''));

            $itemCode = trim((string) ($item['code'] ?? ''));
            $itemCodeAux = !empty($item['code_aux']) && trim($item['code_aux']) !== '-'
                ? trim($item['code_aux'])
                : (!empty($itemCode) ? ('LE' . $itemCode) : null);

            // Recordar configuración previa del ítem si ya existe en el sistema (por ID o código/descripción)
            $productId = $item['product_id'] ?? null;
            $existingProduct = null;
            if ($productId && is_numeric($productId)) {
                $existingProduct = Product::withTrashed()->find($productId);
            }
            if (!$existingProduct) {
                $existingProduct = Product::withTrashed()
                    ->where(function ($query) use ($itemCode, $itemCodeAux, $item) {
                        if (!empty($itemCode)) {
                            $query->where('sku', $itemCode);
                        }
                        if (!empty($itemCodeAux) && $itemCodeAux !== '-') {
                            $query->orWhere('code_aux', $itemCodeAux);
                        }
                        if (!empty($item['description'])) {
                            $query->orWhere('description', $item['description']);
                        }
                    })
                    ->first();
            }

            // Si el ítem ya existe en el sistema y no se envió un usage_type explícito, recordar la clasificación previa
            if (empty($rawUsageType)) {
                if ($existingProduct && !empty($existingProduct->usage_type)) {
                    $rawUsageType = $existingProduct->usage_type;
                    $rawItemType = ($rawUsageType === Product::USAGE_WORKSHOP_STOCK_UNIT) ? 3 : (($rawUsageType === Product::USAGE_WORKSHOP_CONSUMABLE) ? 2 : 1);
                } else {
                    if ($rawItemType === 3) {
                        $rawUsageType = Product::USAGE_WORKSHOP_STOCK_UNIT;
                    } elseif ($rawItemType === 2) {
                        $rawUsageType = Product::USAGE_WORKSHOP_CONSUMABLE;
                    } else {
                        $rawUsageType = Product::USAGE_COMMERCIAL_PRODUCT;
                    }
                }
            } else {
                // Sincronizar item_type según usage_type
                if ($rawUsageType === Product::USAGE_WORKSHOP_STOCK_UNIT) {
                    $rawItemType = 3;
                } elseif ($rawUsageType === Product::USAGE_WORKSHOP_CONSUMABLE) {
                    $rawItemType = 2;
                } else {
                    $rawUsageType = Product::USAGE_COMMERCIAL_PRODUCT;
                    $rawItemType = 1;
                }
            }

            $isCommercial = $rawUsageType === Product::USAGE_COMMERCIAL_PRODUCT;
            $isWorkshopStock = $rawUsageType === Product::USAGE_WORKSHOP_STOCK_UNIT;
            $isWorkshopConsumable = $rawUsageType === Product::USAGE_WORKSHOP_CONSUMABLE;

            if (empty($itemCode)) {
                $prefix = $isCommercial ? 'PROD-' : ($isWorkshopStock ? 'INS-BOX-' : 'INS-');
                $itemCode = $prefix . strtoupper(Str::random(6));
            }

            if (empty($itemCodeAux) && !empty($itemCode)) {
                $itemCodeAux = 'LE' . $itemCode;
            }

            $itemQty = (float) ($item['quantity'] ?? 1);
            $itemUnitPrice = (float) ($item['unit_price'] ?? 0);
            $itemSubtotal = (float) ($item['subtotal'] ?? 0);
            $itemDiscount = (float) ($item['discount'] ?? 0);
            $itemTax = (float) ($item['tax'] ?? 0);
            $itemTotal = (float) ($item['total'] ?? ($itemSubtotal + $itemTax));

            // Si es Nota de Venta, no hay IVA por normativa RIMPE
            if ($documentType === Invoice::DOC_SALE_NOTE) {
                $itemTax = 0.0;
                $itemTotal = $itemSubtotal;
            }

            if ($itemUnitPrice <= 0 && $itemQty > 0 && $itemSubtotal > 0) {
                $itemUnitPrice = round(($itemSubtotal + $itemDiscount) / $itemQty, 4);
            }

            $realPurchasePrice = $itemQty > 0 ? round($itemSubtotal / $itemQty, 4) : $itemUnitPrice;
            $salePrice = round($realPurchasePrice * 1.55, 2);

            $profit = max(0.0, $salePrice - $realPurchasePrice);
            $maxDiscount = round($profit * 0.50, 2);
            $discountPercentage = $salePrice > 0 ? round(($maxDiscount / $salePrice) * 100, 2) : 0.0;
            $brand = !empty($item['brand']) ? trim($item['brand']) : ($isCommercial ? 'SM' : 'N/A');
            $itemCatId = $isCommercial ? ($item['product_categorie_id'] ?? $defaultCatId) : null;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'code' => $itemCode,
                'description' => $item['description'],
                'quantity' => $itemQty,
                'unit_price' => $itemUnitPrice,
                'subtotal' => $itemSubtotal,
                'discount' => $itemDiscount,
                'tax' => $itemTax,
                'total' => $itemTotal,
                'item_type' => $rawItemType,
                'usage_type' => $rawUsageType,
                'product_categorie_id' => $itemCatId,
                'brand' => $brand,
            ]);

            $selectedForInventory = !isset($item['selected_for_inventory'])
                || $item['selected_for_inventory'] === true
                || $item['selected_for_inventory'] === 1
                || $item['selected_for_inventory'] === '1'
                || $item['selected_for_inventory'] === 'true';

            // 1. PRODUCTO COMERCIAL PARA VENTA
            if ($isCommercial && $selectedForInventory) {
                $hasCommercialProduct = true;
                $commercialTotal += $itemTotal;

                $product = $existingProduct;
                $finalBrand = !empty($item['brand']) ? trim($item['brand']) : ($product?->brand ?? 'SM');

                if (!$product) {
                    Product::create([
                        'description' => $item['description'],
                        'sku' => $itemCode,
                        'code_aux' => $itemCodeAux,
                        'product_categorie_id' => $itemCatId,
                        'warehouse_id' => $defaultWarehouseId,
                        'unit_id' => $defaultUnitId,
                        'supplier_id' => $supplierId,
                        'price' => $realPurchasePrice,
                        'price_sale' => $salePrice,
                        'purchase_price' => $realPurchasePrice,
                        'tax_rate' => $defaultTaxRate,
                        'max_discount' => $maxDiscount,
                        'discount_percentage' => $discountPercentage,
                        'brand' => $finalBrand,
                        'stock' => $itemQty,
                        'item_type' => 1,
                        'usage_type' => Product::USAGE_COMMERCIAL_PRODUCT,
                        'min_stock' => 1,
                        'max_stock' => 5,
                        'is_taxable' => 1,
                        'is_gift' => 2,
                        'notes' => 'Creado por Compra Mercadería: ' . $invoiceNumber,
                        'state' => 1,
                    ]);
                } else {
                    if ($product->trashed()) {
                        $product->restore();
                        $product->stock = 0;
                    }

                    $product->stock += $itemQty;
                    $product->state = 1;
                    $product->item_type = 1;
                    $product->usage_type = Product::USAGE_COMMERCIAL_PRODUCT;
                    $product->supplier_id = $supplierId; // Registrar última procedencia de proveedor
                    $product->price = $realPurchasePrice;
                    $product->purchase_price = $realPurchasePrice;
                    $product->price_sale = $salePrice;
                    $product->max_discount = $maxDiscount;
                    $product->discount_percentage = $discountPercentage;
                    if (!empty($item['brand'])) {
                        $product->brand = $finalBrand;
                    }
                    if (empty($product->code_aux) && !empty($itemCodeAux)) {
                        $product->code_aux = $itemCodeAux;
                    }
                    if (!empty($itemCatId)) {
                        $product->product_categorie_id = $itemCatId;
                    }
                    $product->save();
                }
            }
            // 2. INSUMO POR CAJA/ENVASE (STOCK INTERNO DE TALLER)
            elseif ($isWorkshopStock && $selectedForInventory) {
                $workshopTotal += $itemTotal;

                $product = $existingProduct;
                $finalBrand = !empty($item['brand']) ? trim($item['brand']) : ($product?->brand ?? 'N/A');

                if (!$product) {
                    Product::create([
                        'description' => $item['description'],
                        'sku' => $itemCode,
                        'code_aux' => $itemCodeAux,
                        'product_categorie_id' => $defaultCatId,
                        'warehouse_id' => $defaultWarehouseId,
                        'unit_id' => $defaultUnitId,
                        'supplier_id' => $supplierId,
                        'price' => $realPurchasePrice,
                        'price_sale' => $realPurchasePrice,
                        'purchase_price' => $realPurchasePrice,
                        'tax_rate' => $defaultTaxRate,
                        'brand' => $finalBrand,
                        'stock' => $itemQty, // Cantidad de envases/cajas cerradas en taller
                        'item_type' => 3,
                        'usage_type' => Product::USAGE_WORKSHOP_STOCK_UNIT,
                        'min_stock' => 1,
                        'max_stock' => 10,
                        'is_taxable' => 1,
                        'is_gift' => 2,
                        'notes' => 'Insumo Taller por Envase/Caja de Compra: ' . $invoiceNumber,
                        'state' => 1,
                    ]);
                } else {
                    if ($product->trashed()) {
                        $product->restore();
                        $product->stock = 0;
                    }

                    $product->stock += $itemQty;
                    $product->state = 1;
                    $product->item_type = 3;
                    $product->usage_type = Product::USAGE_WORKSHOP_STOCK_UNIT;
                    $product->supplier_id = $supplierId;
                    $product->price = $realPurchasePrice;
                    $product->purchase_price = $realPurchasePrice;
                    if (!empty($item['brand'])) {
                        $product->brand = $finalBrand;
                    }
                    $product->save();
                }

                // Registrar en detalle contable de insumos operativos
                ExpenseDetail::create([
                    'invoice_id' => $invoice->id,
                    'description' => trim((string) $item['description']) . " ({$itemQty} envases/cajas)",
                    'category' => 'Insumos Taller (Stock Envases)',
                    'total' => $itemTotal,
                ]);
            }
            // 3. INSUMO DIRECTO / GASTO OPERATIVO INMEDIATO
            else {
                $workshopTotal += $itemTotal;

                ExpenseDetail::create([
                    'invoice_id' => $invoice->id,
                    'description' => trim((string) $item['description']),
                    'category' => $rawUsageType === Product::USAGE_WORKSHOP_CONSUMABLE ? 'Insumos Directos de Taller' : 'Gasto General',
                    'total' => $itemTotal,
                ]);
            }
        }

        // Si la compra no tiene productos comerciales inventariables, marcar como is_expense = true
        if (!$hasCommercialProduct) {
            $invoice->update(['is_expense' => true]);
        }

        return [
            'hasCommercialProduct' => $hasCommercialProduct,
            'commercialTotal' => $commercialTotal,
            'workshopTotal' => $workshopTotal,
        ];
    }
}

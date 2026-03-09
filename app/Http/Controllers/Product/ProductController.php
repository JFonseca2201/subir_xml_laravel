<?php

namespace App\Http\Controllers\Product;

use App\Exports\Product\ProductDownloadExcel;
use App\Http\Controllers\Controller;
use App\Models\Config\ProductCategorie;
use App\Models\Config\Unit;
use App\Models\Config\Warehouse;
use App\Models\Supplier;
use App\Models\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Obtener parámetros de búsqueda
            $search = $request->get('search', '');
            $categorie_id = $request->get('categorie_id');
            $warehouse_id = $request->get('warehouse_id');
            $unit_id = $request->get('unit_id');
            $disponibilidad = $request->get('disponibilidad');
            $is_gift = $request->get('is_gift');

            // Aplicar filtros usando el scope
            $products = Product::with(['categorie', 'warehouse', 'unit', 'supplier'])
                ->filterAdvance($search, $categorie_id, $warehouse_id, $unit_id, $disponibilidad, $is_gift)
                ->orderBy('id', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 200,
                'message' => 'Productos obtenidos exitosamente',
                'products' => $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'description' => strtoupper(trim($product->description)),
                        'sku' => strtoupper(trim($product->sku)),
                        'imagen' => $product->imagen ? env('APP_URL') . 'storage/' . $product->imagen : null,
                        'code_aux' => strtoupper(trim($product->code_aux)),
                        'uses' => $product->uses,
                        'product_categorie_id' => $product->product_categorie_id,
                        'warehouse_id' => $product->warehouse_id,
                        'unit_id' => $product->unit_id,
                        'supplier_id' => $product->supplier_id,
                        'price' => (float) $product->price,
                        'price_sale' => (float) $product->price_sale,
                        'purchase_price' => (float) $product->purchase_price,
                        'tax_rate' => (float) $product->tax_rate,
                        'max_discount' => (float) $product->max_discount,
                        'discount_percentage' => (float) $product->discount_percentage,
                        'brand' => strtoupper(trim($product->brand)),
                        'stock' => (float) $product->stock,
                        'item_type' => (int) $product->item_type,
                        'min_stock' => (float) $product->min_stock,
                        'max_stock' => (float) $product->max_stock,
                        'is_taxable' => (bool) $product->is_taxable,
                        'is_gift' => (int) $product->is_gift,
                        'notes' => trim($product->notes),
                        'state' => (int) $product->state,
                        'categorie' => $product->categorie ? [
                            'id' => $product->categorie->id,
                            'title' => $product->categorie->title,
                        ] : null,
                        'warehouse' => $product->warehouse ? [
                            'id' => $product->warehouse->id,
                            'name' => $product->warehouse->name,
                        ] : null,
                        'unit' => $product->unit ? [
                            'id' => $product->unit->id,
                            'name' => strtoupper(trim($product->unit->name)),
                        ] : null,
                        'supplier' => $product->supplier ? [
                            'id' => $product->supplier->id,
                            'name' => $product->supplier->name,
                        ] : null,
                        'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                        'updated_at' => $product->updated_at->format('Y/m/d h:i:s'),
                    ];
                }),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener los productos',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function config()
    {
        try {
            $categories = ProductCategorie::where('state', 1)
                ->select('id', 'title')
                ->orderBy('title', 'asc')
                ->get();

            $suppliers = Supplier::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            $warehouses = Warehouse::where('state', 0)
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            $units = Unit::where('state', 1)
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => 200,
                'message' => 'Configuración obtenida exitosamente',
                'data' => [
                    'categories' => $categories,
                    'suppliers' => $suppliers,
                    'warehouses' => $warehouses,
                    'units' => $units,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener la configuración',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    public function download_excel(Request $request)
    {

        try {
            // Obtener parámetros de búsqueda
            $search = $request->get('search', '');
            $categorie_id = $request->get('categorie_id');
            $warehouse_id = $request->get('warehouse_id');
            $unit_id = $request->get('unit_id');
            $disponibilidad = $request->get('disponibilidad');
            $is_gift = $request->get('is_gift');

            // Aplicar filtros usando el scope
            $products = Product::with(['categorie', 'warehouse', 'unit', 'supplier'])
                ->filterAdvance($search, $categorie_id, $warehouse_id, $unit_id, $disponibilidad, $is_gift)
                ->orderBy('id', 'desc')
                ->get();

            return Excel::download(new ProductDownloadExcel($products), 'products.xlsx');
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener los productos',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validar que el producto no exista por descripción
            $is_product_exists = Product::where('description', $request->description)->first();
            if ($is_product_exists) {
                return response()->json([
                    'message' => 403,
                    'message_text' => 'EL NOMBRE DEL PRODUCTO YA EXISTE',
                ]);
            }

            // Validar que el SKU no exista
            if ($request->sku) {
                $is_product_sku_exists = Product::where('sku', $request->sku)->first();
                if ($is_product_sku_exists) {
                    return response()->json([
                        'message' => 403,
                        'message_text' => 'EL CÓDIGO ÚNICO DEL PRODUCTO YA EXISTE',
                    ]);
                }
            }

            // Validar campos requeridos
            $request->validate([
                'description' => 'required|string|max:255',
                'sku' => 'nullable|string|max:50|unique:products,sku',
                'product_categorie_id' => 'required|integer|exists:product_categories,id',
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'unit_id' => 'nullable|integer|exists:units,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'price' => 'required|numeric|min:0',
                'price_sale' => 'required|numeric|min:0',
                'purchase_price' => 'required|numeric|min:0',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'max_discount' => 'required|numeric|min:0',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'brand' => 'nullable|string|max:100',
                'stock' => 'required|numeric|min:0',
                'item_type' => 'required|integer|min:1|max:99',
                'min_stock' => 'required|numeric|min:0',
                'max_stock' => 'required|numeric|min:0',
                'is_taxable' => 'required|integer|in:1,2',
                'is_gift' => 'required|integer|in:1,2',
                'notes' => 'nullable|string|max:2000',
                'state' => 'required|integer|in:1,2',
                'imagen' => 'nullable|image|max:2048',
            ]);

            // Crear el producto
            $product = Product::create($request->all());

            // Manejar la imagen si se envía
            if ($request->hasFile('imagen')) {
                $path = Storage::putFile('products', $request->file('imagen'));
                $product->update([
                    'imagen' => $path,
                ]);
            }

            // Cargar relaciones para la respuesta
            $product->load(['categorie', 'warehouse', 'unit', 'supplier']);

            return response()->json([
                'message' => 200,
                'message_text' => 'Producto creado exitosamente',
                'product' => [
                    'id' => $product->id,
                    'description' => strtoupper(trim($product->description)),
                    'sku' => strtoupper(trim($product->sku)),
                    'imagen' => $product->imagen,
                    'code_aux' => strtoupper(trim($product->code_aux)),
                    'uses' => $product->uses,
                    'product_categorie_id' => $product->product_categorie_id,
                    'warehouse_id' => $product->warehouse_id,
                    'unit_id' => $product->unit_id,
                    'supplier_id' => $product->supplier_id,
                    'price' => (float) $product->price,
                    'price_sale' => (float) $product->price_sale,
                    'purchase_price' => (float) $product->purchase_price,
                    'tax_rate' => (float) $product->tax_rate,
                    'max_discount' => (float) $product->max_discount,
                    'discount_percentage' => (float) $product->discount_percentage,
                    'brand' => strtoupper(trim($product->brand)),
                    'stock' => (float) $product->stock,
                    'item_type' => (int) $product->item_type,
                    'min_stock' => (float) $product->min_stock,
                    'max_stock' => (float) $product->max_stock,
                    'is_taxable' => (bool) $product->is_taxable,
                    'is_gift' => (int) $product->is_gift,
                    'notes' => trim($product->notes),
                    'state' => (int) $product->state,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'warehouse' => $product->warehouse ? [
                        'id' => $product->warehouse->id,
                        'name' => $product->warehouse->name,
                    ] : null,
                    'unit' => $product->unit ? [
                        'id' => $product->unit->id,
                        'name' => strtoupper(trim($product->unit->name)),
                    ] : null,
                    'supplier' => $product->supplier ? [
                        'id' => $product->supplier->id,
                        'name' => $product->supplier->name,
                    ] : null,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                    'updated_at' => $product->updated_at->format('Y/m/d h:i:s'),
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 422,
                'message_text' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 500,
                'message_text' => 'Error al crear el producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $product = Product::with(['categorie', 'warehouse', 'unit', 'supplier'])
                ->findOrFail($id);

            return response()->json([
                'status' => 200,
                'message' => 'Producto obtenido exitosamente',
                'product' => [
                    'id' => $product->id,
                    'description' => strtoupper(trim($product->description)),
                    'sku' => strtoupper(trim($product->sku)),
                    'imagen' => $product->imagen ? env('APP_URL') . 'storage/' . $product->imagen : null,
                    'code_aux' => strtoupper(trim($product->code_aux)),
                    'uses' => $product->uses,
                    'product_categorie_id' => $product->product_categorie_id,
                    'warehouse_id' => $product->warehouse_id,
                    'unit_id' => $product->unit_id,
                    'supplier_id' => $product->supplier_id,
                    'price' => (float) $product->price,
                    'price_sale' => (float) $product->price_sale,
                    'purchase_price' => (float) $product->purchase_price,
                    'tax_rate' => (float) $product->tax_rate,
                    'max_discount' => (float) $product->max_discount,
                    'discount_percentage' => (float) $product->discount_percentage,
                    'brand' => strtoupper(trim($product->brand)),
                    'stock' => (float) $product->stock,
                    'item_type' => (int) $product->item_type,
                    'min_stock' => (float) $product->min_stock,
                    'max_stock' => (float) $product->max_stock,
                    'is_taxable' => (bool) $product->is_taxable,
                    'is_gift' => (int) $product->is_gift,
                    'notes' => trim($product->notes),
                    'state' => (int) $product->state,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'warehouse' => $product->warehouse ? [
                        'id' => $product->warehouse->id,
                        'name' => $product->warehouse->name,
                    ] : null,
                    'unit' => $product->unit ? [
                        'id' => $product->unit->id,
                        'name' => strtoupper(trim($product->unit->name)),
                    ] : null,
                    'supplier' => $product->supplier ? [
                        'id' => $product->supplier->id,
                        'name' => $product->supplier->name,
                    ] : null,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                    'updated_at' => $product->updated_at->format('Y/m/d h:i:s'),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
                'error' => 'El producto con ID ' . $id . ' no existe',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener el producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $product = Product::findOrFail($id);

            // Validar que el producto no exista por descripción (excepto el actual)
            if ($request->description && $request->description !== $product->description) {
                $is_product_exists = Product::where('description', $request->description)
                    ->where('id', '!=', $id)
                    ->first();
                if ($is_product_exists) {
                    return response()->json([
                        'message' => 403,
                        'message_text' => 'EL NOMBRE DEL PRODUCTO YA EXISTE',
                    ]);
                }
            }

            // Validar que el SKU no exista (excepto el actual)
            if ($request->sku && $request->sku !== $product->sku) {
                $is_product_sku_exists = Product::where('sku', $request->sku)
                    ->where('id', '!=', $id)
                    ->first();
                if ($is_product_sku_exists) {
                    return response()->json([
                        'message' => 403,
                        'message_text' => 'EL CÓDIGO ÚNICO DEL PRODUCTO YA EXISTE',
                    ]);
                }
            }

            // Validar campos
            $request->validate([
                'description' => 'required|string|max:255',
                'sku' => 'nullable|string|max:50|unique:products,sku,' . $id,
                'product_categorie_id' => 'required|integer|exists:product_categories,id',
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'unit_id' => 'nullable|integer|exists:units,id',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'price' => 'required|numeric|min:0',
                'price_sale' => 'required|numeric|min:0',
                'purchase_price' => 'required|numeric|min:0',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'max_discount' => 'required|numeric|min:0',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'brand' => 'nullable|string|max:100',
                'stock' => 'required|numeric|min:0',
                'item_type' => 'required|integer|min:1|max:99',
                'min_stock' => 'required|numeric|min:0',
                'max_stock' => 'required|numeric|min:0',
                'is_taxable' => 'required|integer|in:1,2',
                'is_gift' => 'required|integer|in:1,2',
                'notes' => 'nullable|string|max:2000',
                'state' => 'required|integer|in:1,2',
                'imagen' => 'nullable|image|max:2048',
            ]);

            // Actualizar el producto
            $product->update($request->all());

            // Manejar la imagen si se envía
            if ($request->hasFile('imagen')) {
                // Eliminar imagen anterior si existe
                if ($product->imagen) {
                    Storage::delete($product->imagen);
                }

                $path = Storage::putFile('products', $request->file('imagen'));
                $product->update([
                    'imagen' => $path,
                ]);
            }

            // Cargar relaciones para la respuesta
            $product->load(['categorie', 'warehouse', 'unit', 'supplier']);

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado exitosamente',
                'product' => [
                    'id' => $product->id,
                    'description' => strtoupper(trim($product->description)),
                    'sku' => strtoupper(trim($product->sku)),
                    'imagen' => $product->imagen ? env('APP_URL') . 'storage/' . $product->imagen : null,
                    'code_aux' => strtoupper(trim($product->code_aux)),
                    'uses' => $product->uses,
                    'product_categorie_id' => $product->product_categorie_id,
                    'warehouse_id' => $product->warehouse_id,
                    'unit_id' => $product->unit_id,
                    'supplier_id' => $product->supplier_id,
                    'price' => (float) $product->price,
                    'price_sale' => (float) $product->price_sale,
                    'purchase_price' => (float) $product->purchase_price,
                    'tax_rate' => (float) $product->tax_rate,
                    'max_discount' => (float) $product->max_discount,
                    'discount_percentage' => (float) $product->discount_percentage,
                    'brand' => strtoupper(trim($product->brand)),
                    'stock' => (float) $product->stock,
                    'item_type' => (int) $product->item_type,
                    'min_stock' => (float) $product->min_stock,
                    'max_stock' => (float) $product->max_stock,
                    'is_taxable' => (bool) $product->is_taxable,
                    'is_gift' => (int) $product->is_gift,
                    'notes' => trim($product->notes),
                    'state' => (int) $product->state,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'warehouse' => $product->warehouse ? [
                        'id' => $product->warehouse->id,
                        'name' => $product->warehouse->name,
                    ] : null,
                    'unit' => $product->unit ? [
                        'id' => $product->unit->id,
                        'name' => strtoupper(trim($product->unit->name)),
                    ] : null,
                    'supplier' => $product->supplier ? [
                        'id' => $product->supplier->id,
                        'name' => $product->supplier->name,
                    ] : null,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                    'updated_at' => $product->updated_at->format('Y/m/d h:i:s'),
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 422,
                'message_text' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 404,
                'message_text' => 'Producto no encontrado',
                'error' => 'El producto con ID ' . $id . ' no existe',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 500,
                'message_text' => 'Error al actualizar el producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);

            // Eliminar imagen si existe
            /* if ($product->imagen) {
                Storage::delete($product->imagen);
            } */

            // Eliminar el producto (soft delete)
            $product->delete();

            return response()->json([
                'message' => 200,
                'message_text' => 'Producto eliminado exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 404,
                'message_text' => 'Producto no encontrado',
                'error' => 'El producto con ID ' . $id . ' no existe',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 500,
                'message_text' => 'Error al eliminar el producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

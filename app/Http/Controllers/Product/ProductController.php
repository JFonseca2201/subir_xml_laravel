<?php

namespace App\Http\Controllers\Product;

use App\Exports\Product\ProductDownloadExcel;
use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductCollection;
use App\Http\Resources\Product\ProductResource;
use App\Models\Config\ProductCategorie;
use App\Models\Config\Unit;
use App\Models\Config\Warehouse;
use App\Models\Supplier;
use App\Models\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                'total' => $products->total(),
                'total_page' => $products->lastPage(),
                'products' => ProductCollection::make($products),
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
            Log::info('Creating product with data:', $request->all());
            $product = Product::create($request->all());
            Log::info('Product created successfully:', ['id' => $product->id, 'description' => $product->description]);

            // Manejar la imagen si se envía
            if ($request->hasFile('imagen')) {
                Log::info('Image file detected:', ['filename' => $request->file('imagen')->getClientOriginalName()]);
                $path = Storage::putFile('products', $request->file('imagen'));
                $product->update([
                    'imagen' => $path,
                ]);
                Log::info('Image saved:', ['path' => $path]);
            }

            // Cargar relaciones para la respuesta
            Log::info('Loading relations...');
            $product->load(['categorie', 'warehouse', 'unit', 'supplier']);
            Log::info('Relations loaded successfully');

            // DEBUG: Mostrar datos del producto antes de la respuesta
            Log::info('Product data for response:', [
                'id' => $product->id,
                'description' => $product->description,
                'sku' => $product->sku,
                'categorie' => $product->categorie,
                'warehouse' => $product->warehouse,
                'unit' => $product->unit,
                'supplier' => $product->supplier,
            ]);

            $response = [
                'status' => 200,
                'message' => 'Producto creado exitosamente',
                'product' => new ProductResource($product),
            ];

            Log::info('Final response prepared:', $response);
            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el producto',
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
                'product' => new ProductResource($product),
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

            $product->update($request->all());

            if ($request->hasFile('imagen')) {
                if ($product->imagen) {
                    Storage::delete($product->imagen);
                }

                $path = Storage::putFile('products', $request->file('imagen'));
                $product->update([
                    'imagen' => $path,
                ]);
            }

            $product->load(['categorie', 'warehouse', 'unit', 'supplier']);

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado exitosamente',
                'product' => new ProductResource($product),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
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

<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $categorie_id = $request->get('categorie_id');

            $products = Product::with('categorie')
                ->when($search, function ($query, $search) {
                    return $query->where('name', 'ilike', '%' . $search . '%');
                })
                ->when($categorie_id, function ($query, $categorie_id) {
                    return $query->where('categorie_id', $categorie_id);
                })
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 200,
                'products' => $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => strtoupper(trim($product->name)),
                        'description' => trim($product->description),
                        'categorie_id' => $product->categorie_id,
                        'categorie' => $product->categorie ? [
                            'id' => $product->categorie->id,
                            'title' => $product->categorie->title,
                        ] : null,
                        'price' => (float) $product->price,
                        'stock' => (int) $product->stock,
                        'state' => (int) $product->state,
                        'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                    ];
                }),
            ]);
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
            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50|unique:products,code',
                'description' => 'nullable|string|max:1000',
                'categorie_id' => 'required|integer|exists:product_categories,id',
                'price' => 'required|numeric|min:0',
                'purchase_price' => 'required|numeric|min:0',
                'wholesale_price' => 'required|numeric|min:0',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'barcode' => 'nullable|string|max:50|unique:products,barcode',
                'sku' => 'nullable|string|max:50|unique:products,sku',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'stock' => 'required|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'dimensions_length' => 'nullable|numeric|min:0',
                'dimensions_width' => 'nullable|numeric|min:0',
                'dimensions_height' => 'nullable|numeric|min:0',
                'unit_of_measure' => 'required|string|max:20',
                'item_type' => 'required|integer|min:1|max:99',
                'min_stock' => 'required|numeric|min:0',
                'max_stock' => 'required|numeric|min:0',
                'is_taxable' => 'required|boolean',
                'is_active' => 'required|boolean',
                'notes' => 'nullable|string|max:2000',
                'state' => 'required|integer|in:1,2',
            ]);

            // Aplicar trim y uppercase
            $request->merge([
                'name' => strtoupper(trim($request->name)),
                'code' => strtoupper(trim($request->code)),
                'description' => trim($request->description),
                'barcode' => trim($request->barcode),
                'sku' => strtoupper(trim($request->sku)),
                'brand' => strtoupper(trim($request->brand)),
                'model' => strtoupper(trim($request->model)),
                'unit_of_measure' => strtoupper(trim($request->unit_of_measure)),
                'notes' => trim($request->notes),
            ]);

            // Controlar el autoincrementable
            $maxId = Product::max('id') ?? 0;
            DB::statement('ALTER TABLE products AUTO_INCREMENT = ' . ($maxId + 1));

            $product = Product::create($request->all());

            // Cargar relación para la respuesta
            $product->load('categorie');

            return response()->json([
                'status' => 200,
                'message' => 'Producto creado exitosamente',
                'product' => [
                    'id' => $product->id,
                    'name' => strtoupper(trim($product->name)),
                    'description' => trim($product->description),
                    'categorie_id' => $product->categorie_id,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'state' => (int) $product->state,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                ],
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
            $product = Product::with('categorie')->findOrFail($id);

            return response()->json([
                'status' => 200,
                'product' => [
                    'id' => $product->id,
                    'name' => strtoupper(trim($product->name)),
                    'description' => trim($product->description),
                    'categorie_id' => $product->categorie_id,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'state' => (int) $product->state,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
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
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'categorie_id' => 'required|integer|exists:product_categories,id',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'state' => 'required|integer|in:1,2',
            ]);

            // Aplicar trim y uppercase
            $request->merge([
                'name' => strtoupper(trim($request->name)),
                'description' => trim($request->description),
            ]);

            $product = Product::findOrFail($id);
            $product->update($request->all());

            // Cargar relación para la respuesta
            $product->load('categorie');

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado exitosamente',
                'product' => [
                    'id' => $product->id,
                    'name' => strtoupper(trim($product->name)),
                    'description' => trim($product->description),
                    'categorie_id' => $product->categorie_id,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'title' => $product->categorie->title,
                    ] : null,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'state' => (int) $product->state,
                    'created_at' => $product->created_at->format('Y/m/d h:i:s'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el producto',
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
            $product->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Producto eliminado exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // No implementado para API
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // No implementado para API
    }
}

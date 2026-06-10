<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\ProductCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductCategorieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);

        //$categories = ProductCategorie::where('title', 'ilike', '%' . $search . '%')->orderBy('title', 'ASC')->get();
        $categories = ProductCategorie::where('title', 'LIKE', '%' . $search . '%')->orderBy('title', 'ASC')->paginate($perPage);
        return response()->json([
            'categories' => $categories->map(function ($categorie) {
                return [
                    'id' => $categorie->id,
                    'title' => $categorie->title,
                    'state' => (int) $categorie->state,
                    'imagen' => $categorie->imagen ? env('APP_URL') . 'storage/' . $categorie->imagen : null,
                    'created_at' => $categorie->created_at->format('Y-m-d h:i A'),
                ];
            }),
            'total' => $categories->total(),
            'per_page' => $categories->perPage(),
            'current_page' => $categories->currentPage(),
            'total_pages' => $categories->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255|unique:product_categories,title',
            'state' => 'nullable|integer|in:0,1',
            'image' => 'nullable|image|max:2048',
        ]);

        $is_categorie_exists = ProductCategorie::where('title', $request->title)->first();
        if ($is_categorie_exists) {
            return response()->json([
                'message' => 403,
                'message_text' => 'LA CATEGORIA YA EXISTE',
            ]);
        }

        $data = $request->only(['title', 'state']);
        if ($request->hasFile('image')) {
            $path = Storage::putFile('categories', $request->file('image'));
            $data['imagen'] = $path;
        }

        $categorie = ProductCategorie::create($data);

        return response()->json([
            'message' => 200,
            'categorie' => [
                'id' => $categorie->id,
                'title' => $categorie->title,
                'state' => (int) $categorie->state,
                'imagen' => $categorie->imagen ? env('APP_URL') . 'storage/' . $categorie->imagen : null,
                'created_at' => $categorie->created_at->format('Y-m-d h:i A'),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:255|unique:product_categories,title,' . $id,
            'state' => 'nullable|integer|in:0,1',
            'image' => 'nullable|image|max:2048',
        ]);

        $is_categorie_exists = ProductCategorie::where('title', $request->title)->where('id', '<>', $id)->first();
        if ($is_categorie_exists) {
            return response()->json([
                'message' => 403,
                'message_text' => 'LA CATEGORIA YA EXISTE',
            ]);
        }

        $categorie = ProductCategorie::findOrFail($id);
        $data = $request->only(['title', 'state']);
        if ($request->hasFile('image')) {
            if ($categorie->imagen) {
                Storage::delete($categorie->imagen);
            }
            $path = Storage::putFile('categories', $request->file('image'));
            $data['imagen'] = $path;
        }
        $categorie->update($data);

        return response()->json([
            'message' => 200,
            'categorie' => [
                'id' => $categorie->id,
                'title' => $categorie->title,
                'state' => (int) $categorie->state,
                'imagen' => $categorie->imagen ? env('APP_URL') . 'storage/' . $categorie->imagen : null,
                'created_at' => $categorie->created_at->format('Y-m-d h:i A'),
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $categorie = ProductCategorie::findOrFail($id);
        $categorie->delete();

        return response()->json([
            'message' => 200,
        ]);
    }
}

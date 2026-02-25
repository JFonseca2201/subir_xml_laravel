<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->search;

            $suppliers = Supplier::where("name", "like", "%{$search}%")
                ->orWhere("ruc", "like", "%{$search}%")
                ->orderBy("id", "desc")
                ->get();

            return response()->json([
                'status' => 200,
                'suppliers' => $suppliers->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'tax_id' => $supplier->tax_id,
                        'ruc' => $supplier->ruc,
                        'name' => $supplier->name,
                        'address' => $supplier->address,
                        'formatted_ruc' => $supplier->formatted_ruc,
                        'created_at' => optional($supplier->created_at)->format('Y-m-d H:i:s'),
                    ];
                }),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_id' => 'required|string|max:255',
                'ruc' => 'required|string|max:13|unique:suppliers,ruc',
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
            ], [
                'tax_id.required' => 'El campo tax_id es obligatorio',
                'tax_id.string' => 'El campo tax_id debe ser texto',
                'tax_id.max' => 'El campo tax_id no debe exceder 255 caracteres',
                'ruc.required' => 'El campo RUC es obligatorio',
                'ruc.string' => 'El campo RUC debe ser texto',
                'ruc.max' => 'El campo RUC no debe exceder 13 caracteres',
                'ruc.unique' => 'El RUC ya está registrado',
                'name.required' => 'El campo nombre es obligatorio',
                'name.string' => 'El campo nombre debe ser texto',
                'name.max' => 'El campo nombre no debe exceder 255 caracteres',
                'address.string' => 'El campo dirección debe ser texto',
                'address.max' => 'El campo dirección no debe exceder 500 caracteres',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Limpiar datos con trim()
            $data = $validator->validated();
            $data['tax_id'] = trim($data['tax_id']);
            $data['ruc'] = trim($data['ruc']);
            $data['name'] = strtoupper(trim($data['name']));
            $data['address'] = isset($data['address']) ? strtoupper(trim($data['address'])) : null;

            // Ajustar autoincrement al último ID + 1
            $maxId = Supplier::max('id') ?? 0;
            DB::statement('ALTER TABLE suppliers AUTO_INCREMENT = ' . ($maxId + 1));

            $supplier = Supplier::create($data);

            return response()->json([
                'status' => 201,
                'supplier' => [
                    'id' => $supplier->id,
                    'tax_id' => $supplier->tax_id,
                    'ruc' => $supplier->ruc,
                    'name' => $supplier->name,
                    'address' => $supplier->address,
                    'formatted_ruc' => $supplier->formatted_ruc,
                    'created_at' => optional($supplier->created_at)->format('Y-m-d H:i:s'),
                ],
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            return response()->json([
                'status' => 200,
                'supplier' => [
                    'id' => $supplier->id,
                    'tax_id' => $supplier->tax_id,
                    'ruc' => $supplier->ruc,
                    'name' => $supplier->name,
                    'address' => $supplier->address,
                    'formatted_ruc' => $supplier->formatted_ruc,
                    'created_at' => optional($supplier->created_at)->format('Y-m-d H:i:s'),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Proveedor no encontrado',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'tax_id' => 'nullable|string|max:255',
                'ruc' => 'nullable|string|max:13|unique:suppliers,ruc,' . $supplier->id,
                'name' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
            ], [
                'tax_id.string' => 'El campo tax_id debe ser texto',
                'tax_id.max' => 'El campo tax_id no debe exceder 255 caracteres',
                'ruc.string' => 'El campo RUC debe ser texto',
                'ruc.max' => 'El campo RUC no debe exceder 13 caracteres',
                'ruc.unique' => 'El RUC ya está registrado',
                'name.string' => 'El campo nombre debe ser texto',
                'name.max' => 'El campo nombre no debe exceder 255 caracteres',
                'address.string' => 'El campo dirección debe ser texto',
                'address.max' => 'El campo dirección no debe exceder 500 caracteres',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Limpiar datos con trim()
            $data = $validator->validated();
            if (isset($data['tax_id'])) {
                $data['tax_id'] = trim($data['tax_id']);
            }
            if (isset($data['ruc'])) {
                $data['ruc'] = trim($data['ruc']);
            }
            if (isset($data['name'])) {
                $data['name'] = strtoupper(trim($data['name']));
            }
            if (isset($data['address'])) {
                $data['address'] = strtoupper(trim($data['address']));
            }

            $supplier->update($data);

            return response()->json([
                'status' => 200,
                'supplier' => [
                    'id' => $supplier->id,
                    'tax_id' => $supplier->tax_id,
                    'ruc' => $supplier->ruc,
                    'name' => $supplier->name,
                    'address' => $supplier->address,
                    'formatted_ruc' => $supplier->formatted_ruc,
                    'created_at' => optional($supplier->created_at)->format('Y-m-d H:i:s'),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Proveedor no encontrado',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the last supplier ID.
     */
    public function getLastId()
    {
        try {
            $lastSupplier = Supplier::orderBy('id', 'desc')->first();

            return response()->json([
                'status' => 200,
                'last_id' => $lastSupplier ? $lastSupplier->id : 0,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            $supplier->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Proveedor eliminado correctamente',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Proveedor no encontrado',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\Sucursale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SucursaleController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $sucursal = Sucursale::findOrFail($id);

            return response()->json([
                'status' => 200,
                'sucursal' => $sucursal,
            ], 200);
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
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $sucursal = Sucursale::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string|max:500',
                'ruc' => 'required|string|max:13|unique:sucursales,ruc,' . $id,
                'phone' => 'nullable|string|max:15',
                'email' => 'nullable|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'secuencial_factura' => 'required|string|max:9',
                'serie_factura' => 'required|string|max:7',
                'establecimiento' => 'required|string|max:3',
                'punto_emision' => 'required|string|max:3',
                'ambiente' => 'required|integer|in:1,2',
                'tipo_emision' => 'required|integer|in:1',
                'firma_electronica' => 'nullable|string|max:255',
                'password_firma' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                'obligado_contabilidad' => 'required|in:SI,NO',
                'contribuyente_especial' => 'nullable|string|max:50',
                'status' => 'required|in:active,inactive',
            ]);

            $sucursal->update($request->all());

            return response()->json([
                'status' => 200,
                'message' => 'Sucursal actualizada exitosamente',
                'sucursal' => $sucursal,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}

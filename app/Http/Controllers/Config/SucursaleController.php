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
                'name' => 'required|string|max:255|unique:sucursales,name,' . $id,
                'address' => 'required|string|max:500',
                'ruc' => 'required|string|max:13',
                'phone' => 'nullable|string|max:15',
                'email' => 'nullable|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'secuencial_factura' => 'required|string|max:9',
                'serie_factura' => 'required|string|max:7',
                'establecimiento' => 'required|string|max:3',
                'punto_emision' => 'required|string|max:3',
                'ambiente' => 'required|in:1,2',
                'tipo_emision' => 'required|in:1',
                'firma_electronica' => 'nullable|string|max:255',
                'firma_file' => 'nullable|file|max:10240',
                'password_firma' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                'obligado_contabilidad' => 'required|in:SI,NO,si,no',
                'contribuyente_especial' => 'nullable|string|max:50',
                'status' => 'nullable|string',
            ]);

            $data = $request->only([
                'name',
                'address',
                'ruc',
                'phone',
                'email',
                'trade_name',
                'secuencial_factura',
                'serie_factura',
                'establecimiento',
                'punto_emision',
                'ambiente',
                'tipo_emision',
                'password_firma',
                'obligado_contabilidad',
                'contribuyente_especial',
                'status',
            ]);

            // Procesar archivo de firma electrónica si fue enviado
            if ($request->hasFile('firma_file')) {
                $file = $request->file('firma_file');
                $filename = 'firma_sucursal_' . $sucursal->id . '_' . time() . '.p12';
                $path = $file->storeAs('firmas', $filename);
                $data['firma_electronica'] = $path;
            } elseif ($request->filled('firma_electronica')) {
                $data['firma_electronica'] = $request->firma_electronica;
            }

            $sucursal->update($data);

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

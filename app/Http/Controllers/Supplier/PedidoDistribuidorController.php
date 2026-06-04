<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Supplier\PedidoDistribuidor;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class PedidoDistribuidorController extends Controller
{
    /**
     * Get next sequence number
     */
    public function getNextNumber()
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => \App\Services\SequenceService::getNextPedidoNumber()
        ]);
    }

    /**
     * Obtener los productos asociados a un distribuidor específico.
     */
    public function getProductsBySupplier(Request $request, $supplier_id)
    {
        try {
            $search = $request->get('search', '');

            $query = Product::where('supplier_id', $supplier_id)
                ->where('state', 1); // Solo productos activos

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $products = $query->orderBy('description', 'asc')->get();

            return response()->json([
                'status' => 200,
                'products' => $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'description' => $product->description,
                        'sku' => $product->sku,
                        'purchase_price' => $product->purchase_price,
                        'price_sale' => $product->price_sale,
                        'stock' => $product->stock,
                    ];
                })
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener los productos del distribuidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todos los pedidos a distribuidor con filtros.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $query = PedidoDistribuidor::with(['distribuidor', 'usuario', 'detalles.producto']);

            if (!empty($search)) {
                $query->whereHas('distribuidor', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $pedidos = $query->orderBy('id', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $pedidos
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener el listado de pedidos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el detalle de un pedido a distribuidor.
     */
    public function show($id)
    {
        try {
            $pedido = PedidoDistribuidor::with(['distribuidor', 'usuario', 'detalles.producto'])->findOrFail($id);

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $pedido
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Pedido no encontrado.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener el detalle del pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar un nuevo pedido a distribuidor.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'distribuidor_id' => 'required|exists:suppliers,id',
            'usuario_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio_compra_estimado' => 'required|numeric|min:0',
            'number' => 'nullable|string|unique:pedidos_distribuidor,number',
            'is_draft' => 'nullable|boolean',
        ], [
            'distribuidor_id.required' => 'El distribuidor es obligatorio.',
            'distribuidor_id.exists' => 'El distribuidor seleccionado no es válido.',
            'usuario_id.required' => 'El usuario es obligatorio.',
            'usuario_id.exists' => 'El usuario seleccionado no es válido.',
            'items.required' => 'Debe agregar al menos un producto al pedido.',
            'items.min' => 'Debe agregar al menos un producto al pedido.',
            'items.*.description.required' => 'La descripción del producto es obligatoria.',
            'items.*.producto_id.exists' => 'Uno de los productos seleccionados no es válido.',
            'items.*.cantidad.required' => 'La cantidad es obligatoria.',
            'items.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'items.*.cantidad.min' => 'La cantidad mínima es 1.',
            'items.*.precio_compra_estimado.required' => 'El precio de compra estimado es obligatorio.',
            'items.*.precio_compra_estimado.numeric' => 'El precio debe ser un número.',
            'items.*.precio_compra_estimado.min' => 'El precio no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Errores de validación.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pedido = DB::transaction(function () use ($request) {
                // Calcular el total estimado del pedido
                $total = 0;
                foreach ($request->items as $item) {
                    $total += $item['cantidad'] * $item['precio_compra_estimado'];
                }

                // Crear cabecera del pedido a distribuidor
                $pedido = PedidoDistribuidor::create([
                    'number' => $request->filled('number') ? $request->number : \App\Services\SequenceService::getNextPedidoNumber(),
                    'distribuidor_id' => $request->distribuidor_id,
                    'usuario_id' => $request->usuario_id,
                    'estado' => $request->boolean('is_draft') ? 'draft' : 'pendiente',
                    'total' => $total,
                ]);

                // Registrar los detalles del pedido
                foreach ($request->items as $item) {
                    $pedido->detalles()->create([
                        'producto_id' => $item['producto_id'] ?? null,
                        'description' => $item['description'],
                        'cantidad' => $item['cantidad'],
                        'precio_compra_estimado' => $item['precio_compra_estimado'],
                    ]);
                }

                return $pedido;
            });

            return response()->json([
                'status' => 201,
                'success' => true,
                'message' => 'Pedido a distribuidor generado exitosamente.',
                'data' => $pedido->load(['distribuidor', 'usuario', 'detalles.producto'])
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al generar el pedido a distribuidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generatePDF(Request $request, $id)
    {
        try {
            $pedido = PedidoDistribuidor::with(['distribuidor', 'detalles.producto'])->findOrFail($id);

            if ($request->has('print')) {
                return view('sales.pedido_distribuidor_pdf', compact('pedido'));
            }
            $pdf = Pdf::loadView('sales.pedido_distribuidor_pdf', compact('pedido'));
            return $pdf->stream('pedido_' . str_pad($pedido->id, 5, '0', STR_PAD_LEFT) . '.pdf');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Pedido no encontrado.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al generar el PDF.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un pedido a distribuidor existente.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'distribuidor_id' => 'required|exists:suppliers,id',
            'usuario_id' => 'required|exists:users,id',
            'estado' => 'nullable|string|in:draft,pendiente,por_confirmar,completado,cancelado',
            'number' => 'nullable|string',
            'is_draft' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio_compra_estimado' => 'required|numeric|min:0',
        ], [
            'distribuidor_id.required' => 'El distribuidor es obligatorio.',
            'distribuidor_id.exists' => 'El distribuidor seleccionado no es válido.',
            'usuario_id.required' => 'El usuario es obligatorio.',
            'usuario_id.exists' => 'El usuario seleccionado no es válido.',
            'items.required' => 'Debe agregar al menos un producto al pedido.',
            'items.min' => 'Debe agregar al menos un producto al pedido.',
            'items.*.description.required' => 'La descripción del producto es obligatoria.',
            'items.*.producto_id.exists' => 'Uno de los productos seleccionados no es válido.',
            'items.*.cantidad.required' => 'La cantidad es obligatoria.',
            'items.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'items.*.cantidad.min' => 'La cantidad mínima es 1.',
            'items.*.precio_compra_estimado.required' => 'El precio de compra estimado es obligatorio.',
            'items.*.precio_compra_estimado.numeric' => 'El precio debe ser un número.',
            'items.*.precio_compra_estimado.min' => 'El precio no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Errores de validación.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pedido = PedidoDistribuidor::findOrFail($id);

            $pedido = DB::transaction(function () use ($request, $pedido) {
                // Calcular el total estimado del pedido
                $total = 0;
                foreach ($request->items as $item) {
                    $total += $item['cantidad'] * $item['precio_compra_estimado'];
                }

                // Actualizar cabecera
                $estadoFinal = $request->boolean('is_draft') ? 'draft' : ($request->estado ?? $pedido->estado);
                if ($estadoFinal === 'draft' && $request->has('estado') && $request->estado !== 'draft') {
                    $estadoFinal = $request->estado;
                }

                $pedido->update([
                    'distribuidor_id' => $request->distribuidor_id,
                    'usuario_id' => $request->usuario_id,
                    'estado' => $estadoFinal,
                    'total' => $total,
                ]);

                // Eliminar antiguos detalles y agregar nuevos
                $pedido->detalles()->delete();

                foreach ($request->items as $item) {
                    $pedido->detalles()->create([
                        'producto_id' => $item['producto_id'] ?? null,
                        'description' => $item['description'],
                        'cantidad' => $item['cantidad'],
                        'precio_compra_estimado' => $item['precio_compra_estimado'],
                    ]);
                }

                return $pedido;
            });

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Pedido a distribuidor actualizado exitosamente.',
                'data' => $pedido->load(['distribuidor', 'usuario', 'detalles.producto'])
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Pedido no encontrado.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un pedido a distribuidor.
     */
    public function destroy($id)
    {
        try {
            $pedido = PedidoDistribuidor::findOrFail($id);
            $pedido->delete(); // Eliminará en cascada por constraint DB

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Pedido a distribuidor eliminado exitosamente.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Pedido no encontrado.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar únicamente el estado de un pedido.
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|in:draft,pendiente,por_confirmar,completado,cancelado',
        ], [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Errores de validación.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pedido = PedidoDistribuidor::findOrFail($id);
            $pedido->update([
                'estado' => $request->estado
            ]);

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Estado del pedido actualizado exitosamente.',
                'data' => $pedido->load(['distribuidor', 'usuario', 'detalles.producto'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Pedido no encontrado.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el estado del pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print PDF directly to the configured Windows printer.
     */
    public function printDirect(int $id)
    {
        try {
            $pedido = PedidoDistribuidor::with(['distribuidor', 'detalles.producto'])->findOrFail($id);

            // 1. Generar el PDF y guardarlo en un archivo temporal
            $pdf = Pdf::loadView('sales.pedido_distribuidor_pdf', compact('pedido'));
            $tempFileName = 'temp_pedido_' . $pedido->id . '_' . time() . '.pdf';
            $tempPath = storage_path('app/' . $tempFileName);
            $pdf->save($tempPath);

            // 2. Obtener configuración
            $printerName = env('PRINTER_NAME', 'L5290 Series(Network)');
            $edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

            // 3. Ejecutar comando de impresión en Windows usando msedge
            if (file_exists($edgePath)) {
                $command = sprintf(
                    'start /B "" "%s" --headless --print-to-printer="%s" "%s"',
                    $edgePath,
                    $printerName,
                    $tempPath
                );
                pclose(popen($command, 'r'));
            } else {
                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => 'No se encontró Microsoft Edge en el servidor para realizar la impresión directa.'
                ], 500);
            }

            // 4. Borrar el archivo temporal después de 15 segundos en segundo plano
            dispatch(function () use ($tempPath) {
                sleep(15);
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            })->afterResponse();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Impresión directa enviada a: ' . $printerName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al procesar la impresión directa: ' . $e->getMessage()
            ], 500);
        }
    }
}

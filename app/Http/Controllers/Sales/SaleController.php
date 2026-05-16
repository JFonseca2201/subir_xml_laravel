<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Listar el historial de ventas y cotizaciones con filtros.
     */
    public function index(Request $request)
    {
        try {
            // Iniciamos la consulta cargando las relaciones clave (Eager Loading)
            // Esto evita el problema de consultas N+1 y hace que la API vuele
            $query = Sale::with(['client', 'vehicle', 'user']);

            // 1. Filtro por tipo de documento (quote, sale_note, invoice)
            if ($request->has('document_type') && $request->document_type != '') {
                $query->where('document_type', $request->document_type);
            }

            // 2. Filtro por cliente específico
            if ($request->has('client_id') && $request->client_id != '') {
                $query->where('client_id', $request->client_id);
            }

            // 3. Filtro por rango de fechas de atención (Muy útil para cierres de caja)
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('service_date', [$request->start_date, $request->end_date]);
            }

            // 4. Filtro por estado de pago (paid, partial, pending)
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->where('payment_status', $request->payment_status);
            }

            // Ordenamos para que las más recientes salgan primerito
            // Paginamos de 15 en 15 para que la pantalla del frente cargue al instante
            $sales = $query->orderBy('service_date', 'desc')
                           ->orderBy('id', 'desc')
                           ->paginate(15);

            return response()->json([
                'success' => true,
                'data'    => $sales
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de ventas.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validación estricta de los datos que vienen del Vue 3
        $request->validate([
            'document_type'   => 'required|in:quote,sale_note,invoice',
            'document_number' => 'required|string|unique:sales,document_number',
            'client_id'       => 'required|exists:clients,id',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
            'mileage'         => 'nullable|integer',
            'service_date'    => 'nullable|date',
            'subtotal'        => 'required|numeric',
            'tax_amount'      => 'required|numeric',
            'total'           => 'required|numeric',
            'payment_status'  => 'required|in:paid,partial,pending',
            'is_credited'     => 'required|boolean',
            'payment_method'  => 'required|string',
            'observations'    => 'nullable|string',
            'items'           => 'required|array|min:1', // El carrito no puede estar vacío
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.price'       => 'required|numeric',
            'items.*.discount'    => 'required|numeric',
        ]);

        try {
            // 2. Iniciamos la transacción para asegurar consistencia atómica
            $sale = DB::transaction(function () use ($request) {
                
                // Extraemos el ID del usuario autenticado que está atendiendo en Luxury Evys
                $userId = auth()->id() ?? 1; // Ajustar según tu middleware de auth

                // A. Crear la cabecera de la venta
                $sale = Sale::create([
                    'document_type'   => $request->document_type,
                    'document_number' => $request->document_number,
                    'client_id'       => $request->client_id,
                    'vehicle_id'      => $request->vehicle_id,
                    'user_id'         => $userId,
                    'mileage'         => $request->mileage,
                    'service_date'    => $request->service_date ?? now()->format('Y-m-d'),
                    'subtotal'        => $request->subtotal,
                    'tax_amount'      => $request->tax_amount,
                    'total'           => $request->total,
                    'status'          => $request->document_type === 'quote' ? 'pending' : 'completed',
                    'payment_status'  => $request->payment_status,
                    'is_credited'     => $request->is_credited,
                    'payment_method'  => $request->payment_method,
                    'observations'    => $request->observations,
                ]);

                // B. Registrar cada producto/servicio del detalle
                foreach ($request->items as $item) {
                    $sale->details()->create([
                        'product_id'  => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity'    => $item['quantity'],
                        'price'       => $item['price'],
                        'discount'    => $item['discount'] ?? 0.00,
                        'total'       => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0.00),
                    ]);

                    // 💥 ESPACIO RESERVADO LUNES:
                    // Si $request->document_type !== 'quote', aquí disparamos la resta de Stock
                }

                // 💰 ESPACIO RESERVADO LUNES: ESCALABILIDAD FINANCIERA
                // Si $request->document_type !== 'quote' (Es Nota de Venta o Factura)
                // Aquí llamamos al modelo/servicio de INGRESOS para registrar la entrada de caja automática.
                // Si es crédito ('is_credited' => true), registramos solo el abono inicial si lo hay.

                return $sale;
            });

            // 3. Respuesta exitosa al frontend con el registro completo cargando sus detalles
            return response()->json([
                'success' => true,
                'message' => 'El registro se procesó correctamente.',
                'data'    => $sale->load('details')
            ], 201);

        } catch (Exception $e) {
            // Si algo truena dentro del bloque, el DB::transaction hace rollback automático
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display the specified resource.
     */
    /**
     * Ver el detalle completo de una sola venta o cotización (Para cargar en el frente).
     */
    public function show($id)
    {
        try {
            // Buscamos la venta cargando al mismo tiempo sus detalles, el cliente y el vehículo
            $sale = Sale::with(['details', 'client', 'vehicle'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $sale
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle de la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * Actualizar datos permitidos de una venta o cotización (El procesamiento del Edit).
     */
    public function update(Request $request, $id)
    {
        // 1. Validamos solo los campos que es seguro editar en caliente
        $request->validate([
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'mileage'      => 'nullable|integer',
            'service_date' => 'nullable|date',
            'observations' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        try {
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro no existe.'
                ], 404);
            }

            // Regla de seguridad: Si la venta ya está facturada o anulada, no debería editarse a la ligera
            if ($sale->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una venta que ya ha sido anulada.'
                ], 400);
            }

            // 2. Actualizamos de forma segura los campos operativos del taller
            $sale->update($request->only([
                'vehicle_id',
                'mileage',
                'service_date',
                'observations',
                'payment_method'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'El registro fue actualizado correctamente.',
                'data'    => $sale->load('details')
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            // Buscamos la venta maestra
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro de venta no existe.'
                ], 404);
            }

            // Si ya está anulada, no hacemos nada
            if ($sale->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta ya se encuentra anulada.'
                ], 400);
            }

            DB::transaction(function () use ($sale) {
                // A. Cambiamos el estado a cancelado
                $sale->update([
                    'status' => 'canceled'
                ]);

                // 💥 ESPACIO RESERVADO LUNES: ESCALABILIDAD
                // Aquí disparamos la reversa de Stock para cada producto/servicio del detalle
                // $sale->details()->each(function($detail) {
                //     // Lógica para sumar nuevamente el stock del producto
                // });
            });

            return response()->json([
                'success' => true,
                'message' => 'La venta ha sido anulada correctamente y el estado fue actualizado.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la venta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
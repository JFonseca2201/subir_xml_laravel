<?php

namespace App\Http\Controllers\Api\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder\WorkOrder;
use App\Models\Product\Product;
use App\Services\WorkOrder\WorkOrderSaleSync;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class WorkOrderController extends Controller
{
    /**
     * Get next sequence number
     */
    public function getNextNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Services\SequenceService::getNextWorkOrderNumber()
        ]);
    }
    /**
     * Store a newly created work order in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'user_id' => 'required|exists:users,id',
            'mileage' => 'nullable|integer',
            'fuel_level' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'diagnostic' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.type' => 'required|in:product,service',
            'number' => 'nullable|string|unique:work_orders,number',
            'is_draft' => 'nullable|boolean',
        ]);

        // Usar el número enviado si existe, de lo contrario autogenerar el número de orden de trabajo (OT-0001, OT-0002, etc.)
        if ($request->filled('number')) {
            $validated['number'] = $request->input('number');
        } else {
            $validated['number'] = \App\Services\SequenceService::getNextWorkOrderNumber();
        }
        $validated['status'] = $request->boolean('is_draft') ? 'draft' : 'received';

        if (!$request->boolean('is_draft')) {
            // Validar stock antes de crear la orden de trabajo
            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if (isset($item['type']) && $item['type'] === 'service') {
                        continue;
                    }
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = Product::find($item['product_id']);
                        if ($product && $product->stock < $item['quantity']) {
                            return response()->json([
                                'success' => false,
                                'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                                'error' => 'stock_insufficient'
                            ], 400);
                        }
                    }
                }
            }

            // Validar descuentos máximos
            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = Product::find($item['product_id']);
                        if ($product && $product->max_discount !== null) {
                            $maxDiscountAmount = ($item['quantity'] * $item['unit_price']) * ($product->max_discount / 100);
                            $itemDiscount = isset($item['discount']) ? $item['discount'] : 0;
                            if ($itemDiscount > $maxDiscountAmount) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Descuento excede el máximo permitido para el producto: {$product->description}. Máximo: {$maxDiscountAmount}, Ingresado: {$itemDiscount}",
                                    'error' => 'discount_exceeded'
                                ], 400);
                            }
                        }
                    }
                }
            }
        }

        $workOrder = WorkOrder::create($validated);

        // Guardar los técnicos de la orden de trabajo
        if (isset($validated['technicians']) && is_array($validated['technicians'])) {
            $workOrder->technicians()->attach($validated['technicians']);
        }

        // Guardar los items de la orden de trabajo
        if (isset($validated['items']) && is_array($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $subtotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $workOrder->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $subtotal,
                    'type' => $item['type'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Orden de trabajo creada exitosamente',
            'data' => $workOrder->load(['client', 'vehicle', 'user', 'technicians', 'items'])
        ], 201);
    }

    /**
     * Update the specified work order in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'user_id' => 'required|exists:users,id',
            'mileage' => 'nullable|integer',
            'fuel_level' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'diagnostic' => 'nullable|string',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:employees,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.type' => 'required|in:product,service',
            'is_draft' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->boolean('is_draft') ? 'draft' : 'received';

        if (!$request->boolean('is_draft')) {
            // Validar stock antes de actualizar
            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if (isset($item['type']) && $item['type'] === 'service') {
                        continue;
                    }
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = Product::find($item['product_id']);
                        if ($product && $product->stock < $item['quantity']) {
                            return response()->json([
                                'success' => false,
                                'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                                'error' => 'stock_insufficient'
                            ], 400);
                        }
                    }
                }
            }

            // Validar descuentos
            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = Product::find($item['product_id']);
                        if ($product && $product->max_discount !== null) {
                            $maxDiscountAmount = ($item['quantity'] * $item['unit_price']) * ($product->max_discount / 100);
                            $itemDiscount = isset($item['discount']) ? $item['discount'] : 0;
                            if ($itemDiscount > $maxDiscountAmount) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Descuento excede el máximo permitido para el producto: {$product->description}. Máximo: {$maxDiscountAmount}, Ingresado: {$itemDiscount}",
                                    'error' => 'discount_exceeded'
                                ], 400);
                            }
                        }
                    }
                }
            }
        }

        $workOrder->update($validated);

        // Guardar técnicos
        if (isset($validated['technicians']) && is_array($validated['technicians'])) {
            $workOrder->technicians()->sync($validated['technicians']);
        }

        // Actualizar items
        if (isset($validated['items']) && is_array($validated['items'])) {
            $workOrder->items()->delete();
            foreach ($validated['items'] as $item) {
                $subtotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $workOrder->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $subtotal,
                    'type' => $item['type'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Orden de trabajo actualizada exitosamente',
            'data' => $workOrder->load(['client', 'vehicle', 'user', 'technicians', 'items'])
        ], 200);
    }

    /**
     * Display a listing of work orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with(['client', 'vehicle', 'user', 'sale', 'items', 'technicians']);

        // Filtrar por estado si se proporciona
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrar por cliente si se proporciona
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Ordenar por fecha de creación descendente
        $workOrders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $workOrders
        ]);
    }

    /**
     * Display the specified work order.
     */
    public function show(int $id): JsonResponse
    {
        $workOrder = WorkOrder::with(['client', 'vehicle', 'user', 'technicians', 'items'])
            ->findOrFail($id);

        return response()->json([
            'data' => $workOrder
        ]);
    }

    /**
     * Update the status of a work order.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,received,in_progress,ready,delivered'
        ]);

        $workOrder = WorkOrder::findOrFail($id);
        $workOrder->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Estado de la orden de trabajo actualizado exitosamente',
            'data' => $workOrder->load(['client', 'vehicle', 'user'])
        ]);
    }

    /**
     * Get work orders that are ready but not yet invoiced.
     */
    public function getReadyToInvoice(): JsonResponse
    {
        $readyOrders = WorkOrder::with(['client', 'vehicle', 'user', 'items', 'technicians'])
            ->where('status', 'ready')
            ->whereDoesntHave('sale')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $readyOrders
        ]);
    }

    /**
     * Generate PDF for a work order.
     */
    public function generatePDF(int $id)
    {
        $workOrder = WorkOrder::with(['client', 'vehicle', 'user', 'technicians', 'items', 'sale'])
            ->findOrFail($id);

        // Verificar que la orden de trabajo tenga una venta asociada
        if (!$workOrder->sale) {
            return response()->json([
                'message' => 'La orden de trabajo no tiene una venta asociada'
            ], 400);
        }

        // Calcular totales
        $grossSubtotal = $workOrder->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
        $totalDiscount = $workOrder->items->sum('discount');
        $total = $grossSubtotal - $totalDiscount;

        // Mapear el ID de la marca al nombre de la marca para el PDF
        $vehicleBrands = config('vehicle_brands', []);
        if ($workOrder->vehicle && isset($workOrder->vehicle->brand)) {
            $brandId = $workOrder->vehicle->brand;
            $workOrder->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
        }

        $data = [
            'workOrder' => $workOrder,
            'grossSubtotal' => $grossSubtotal,
            'totalDiscount' => $totalDiscount,
            'total' => $total,
        ];

        $pdf = Pdf::loadView('work-orders.pdf', $data);
        return $pdf->download('orden-trabajo-' . $workOrder->number . '.pdf');
    }
}
<?php

namespace App\Services\WorkOrder;

use App\Models\Sales\Sale;
use App\Models\WorkOrder\WorkOrder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class WorkOrderSaleSync
{
    public static function parseOtNumber(?string $number): int
    {
        if (!$number || !preg_match('/OT-?(\d+)/i', $number, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Obtiene el mayor correlativo OT- usado en órdenes de trabajo y ventas.
     */
    public static function getMaxOtNumber(): int
    {
        $max = 0;

        WorkOrder::withTrashed()->pluck('number')->each(function ($number) use (&$max) {
            $max = max($max, self::parseOtNumber($number));
        });

        Sale::where('document_number', 'like', 'OT-%')
            ->pluck('document_number')
            ->each(function ($number) use (&$max) {
                $max = max($max, self::parseOtNumber($number));
            });

        return $max;
    }

    public static function formatNextNumber(): string
    {
        return 'OT-' . str_pad((string) (self::getMaxOtNumber() + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Valida que la orden esté lista y sin venta asociada.
     */
    public static function assertReadyForInvoicing(int $workOrderId): WorkOrder
    {
        $workOrder = WorkOrder::with(['sale', 'technicians'])->findOrFail($workOrderId);

        if ($workOrder->status !== 'ready') {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'La orden de trabajo debe estar en estado "Listo" para facturar.',
                'error' => 'work_order_not_ready',
            ], 400));
        }

        if ($workOrder->sale) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Esta orden de trabajo ya tiene una venta asociada.',
                'error' => 'work_order_already_invoiced',
            ], 400));
        }

        return $workOrder;
    }

    public static function markAsDelivered(WorkOrder $workOrder): void
    {
        $workOrder->update(['status' => 'delivered']);
    }

    public static function resolveFinanceWorkOrderNumber(?int $workOrderId, string $fallbackDocumentNumber): string
    {
        if (!$workOrderId) {
            return $fallbackDocumentNumber;
        }

        $workOrder = WorkOrder::find($workOrderId);

        return $workOrder?->number ?? $fallbackDocumentNumber;
    }

    /**
     * @return int[]
     */
    public static function resolveTechnicianIds(Request $request, ?WorkOrder $workOrder): array
    {
        if ($request->has('technicians') && is_array($request->technicians)) {
            return array_values(array_unique(array_map('intval', $request->technicians)));
        }

        if ($workOrder) {
            return $workOrder->technicians->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
    }

    /**
     * @param int[] $technicianIds
     */
    public static function syncTechniciansToSale(Sale $sale, array $technicianIds): void
    {
        $sale->technicians()->sync($technicianIds);
    }
}

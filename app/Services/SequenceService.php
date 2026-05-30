<?php

namespace App\Services;

use App\Models\Sales\Sale;
use App\Models\WorkOrder\WorkOrder;
use App\Models\Supplier\PedidoDistribuidor;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Parse the integer from a sequence string like PREFIX-0000123
     */
    private static function parseSequenceNumber(?string $number, string $prefix): int
    {
        if (!$number || !preg_match('/' . preg_quote($prefix) . '-?(\d+)/i', $number, $matches)) {
            return 0;
        }
        return (int) $matches[1];
    }

    /**
     * Get the next sequence number for Direct Sales (V-0000001)
     */
    public static function getNextDirectSaleNumber(): string
    {
        $max = 0;
        
        // Buscamos solo los que tienen el prefijo V- (Ventas Directas)
        Sale::where('document_number', 'like', 'V-%')
            ->pluck('document_number')
            ->each(function ($number) use (&$max) {
                $max = max($max, self::parseSequenceNumber($number, 'V'));
            });

        return 'V-' . str_pad((string) ($max + 1), 7, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next sequence number for Work Orders (OT-0000001)
     */
    public static function getNextWorkOrderNumber(): string
    {
        // Reutilizamos la lógica existente que revisa tanto work_orders como sales vinculadas
        return \App\Services\WorkOrder\WorkOrderSaleSync::formatNextNumber();
    }

    /**
     * Get the next sequence number for Pedidos a Distribuidor (P-0000001)
     */
    public static function getNextPedidoNumber(): string
    {
        $max = 0;
        
        DB::table('pedidos_distribuidor')->where('number', 'like', 'P-%')
            ->pluck('number')
            ->each(function ($number) use (&$max) {
                $max = max($max, self::parseSequenceNumber($number, 'P'));
            });

        return 'P-' . str_pad((string) ($max + 1), 7, '0', STR_PAD_LEFT);
    }
}

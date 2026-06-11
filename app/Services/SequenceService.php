<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Preview generic sequence value WITHOUT incrementing (for frontend display)
     */
    public static function previewNextSequenceValue(string $sequenceName, int $startValue = 0): int
    {
        $sequence = DB::table('sequences')->where('name', $sequenceName)->first();
        return $sequence ? $sequence->current_value + 1 : $startValue + 1;
    }

    /**
     * Generic sequence getter with lock (Must be used inside DB::transaction)
     */
    public static function getNextSequenceValue(string $sequenceName, int $startValue = 0): int
    {
        $sequence = DB::table('sequences')->where('name', $sequenceName)->lockForUpdate()->first();

        if (!$sequence) {
            DB::table('sequences')->insert([
                'name' => $sequenceName,
                'current_value' => $startValue + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $startValue + 1;
        }

        $newValue = $sequence->current_value + 1;
        DB::table('sequences')->where('name', $sequenceName)->update([
            'current_value' => $newValue,
            'updated_at' => now(),
        ]);

        return $newValue;
    }

    /**
     * PREVIEW the unified global sequence number
     */
    public static function previewNextGlobalNumber(): string
    {
        $val = self::previewNextSequenceValue('taller_global_sequence');
        return str_pad((string) $val, 9, '0', STR_PAD_LEFT);
    }

    /**
     * GENERATE the unified global sequence number
     */
    public static function getNextGlobalNumber(): string
    {
        $val = self::getNextSequenceValue('taller_global_sequence');
        return str_pad((string) $val, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Preview the next sequence number for Direct Sales
     */
    public static function previewNextDirectSaleNumber(): string
    {
        return self::previewNextGlobalNumber();
    }

    /**
     * Get the next sequence number for Direct Sales
     */
    public static function getNextDirectSaleNumber(): string
    {
        return self::getNextGlobalNumber();
    }

    /**
     * Preview the next sequence number for Work Orders
     */
    public static function previewNextWorkOrderNumber(): string
    {
        return self::previewNextGlobalNumber();
    }

    /**
     * Get the next sequence number for Work Orders
     */
    public static function getNextWorkOrderNumber(): string
    {
        return self::getNextGlobalNumber();
    }

    /**
     * Get the next sequence number for Pedidos a Distribuidor (P-YYYYMMDDXXX)
     */
    public static function getNextPedidoNumber(): string
    {
        $date = now()->format('Ymd');
        $sequenceName = 'pedidos_sequence_' . $date;
        $val = self::getNextSequenceValue($sequenceName);
        return 'P-' . $date . str_pad((string) $val, 3, '0', STR_PAD_LEFT);
    }
}

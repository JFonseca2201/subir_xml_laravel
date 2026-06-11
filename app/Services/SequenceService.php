<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Generic sequence getter with lock
     */
    private static function getNextSequenceValue(string $sequenceName, int $startValue = 0): int
    {
        return DB::transaction(function () use ($sequenceName, $startValue) {
            $sequence = DB::table('sequences')->where('name', $sequenceName)->lockForUpdate()->first();

            if (!$sequence) {
                $sequenceId = DB::table('sequences')->insertGetId([
                    'name' => $sequenceName,
                    'current_value' => $startValue + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return $startValue + 1;
            }

            $newValue = $sequence->current_value + 1;
            DB::table('sequences')->where('id', $sequence->id)->update([
                'current_value' => $newValue,
                'updated_at' => now(),
            ]);

            return $newValue;
        });
    }

    /**
     * Get the unified global sequence number for Sales and Work Orders
     * Formatted as 9 digits (e.g. 000001846)
     */
    public static function getNextGlobalNumber(): string
    {
        $val = self::getNextSequenceValue('taller_global_sequence');
        return str_pad((string) $val, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next sequence number for Direct Sales
     */
    public static function getNextDirectSaleNumber(): string
    {
        return self::getNextGlobalNumber();
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

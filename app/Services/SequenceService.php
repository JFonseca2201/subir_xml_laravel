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
     * Consume or generate the sequence number safely
     */
    public static function consumeGlobalNumber(?string $requestedNumber = null): string
    {
        $sequenceName = 'taller_global_sequence';
        $sequence = DB::table('sequences')->where('name', $sequenceName)->lockForUpdate()->first();
        $current = $sequence ? $sequence->current_value : 0;

        if (empty($requestedNumber)) {
            $next = $current + 1;
            self::updateSequence($sequenceName, $next, $sequence != null);
            return str_pad((string) $next, 9, '0', STR_PAD_LEFT);
        }

        $requestedInt = (int)$requestedNumber;
        // Check if it's formatted as a standard sequence number or just a number
        if ((string)$requestedInt === $requestedNumber || str_pad((string)$requestedInt, 9, '0', STR_PAD_LEFT) === $requestedNumber) {
            if ($requestedInt <= $current) {
                // Number already taken or old preview, generate a fresh one
                $next = $current + 1;
                self::updateSequence($sequenceName, $next, $sequence != null);
                return str_pad((string) $next, 9, '0', STR_PAD_LEFT);
            } else {
                // Number is ahead of sequence, fast-forward the sequence
                self::updateSequence($sequenceName, $requestedInt, $sequence != null);
                return str_pad((string) $requestedInt, 9, '0', STR_PAD_LEFT);
            }
        }

        // Custom string (e.g. "FACT-999")
        return $requestedNumber;
    }

    private static function updateSequence(string $sequenceName, int $value, bool $exists)
    {
        if ($exists) {
            DB::table('sequences')->where('name', $sequenceName)->update([
                'current_value' => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sequences')->insert([
                'name' => $sequenceName,
                'current_value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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

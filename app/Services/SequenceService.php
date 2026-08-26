<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Formats a sequence number using a prefix and value.
     */
    public static function formatNumber(int $val, ?string $prefix): string
    {
        if ($prefix) {
            return $prefix . '-' . str_pad((string) $val, 7, '0', STR_PAD_LEFT);
        }
        return str_pad((string) $val, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Preview generic sequence value WITHOUT incrementing (for frontend display)
     */
    public static function previewNextSequenceValue(string $type, int $startValue = 0): int
    {
        $sequence = DB::table('sequences')->where('type', $type)->first();
        return $sequence ? $sequence->current_number + 1 : $startValue + 1;
    }

    /**
     * Generic sequence getter with lock (Must be used inside DB::transaction)
     */
    public static function getNextSequenceValue(string $type, int $startValue = 0): int
    {
        $sequence = DB::table('sequences')->where('type', $type)->lockForUpdate()->first();

        if (!$sequence) {
            $prefix = null;

            DB::table('sequences')->insert([
                'type' => $type,
                'current_number' => $startValue + 1,
                'prefix' => $prefix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $startValue + 1;
        }

        $newValue = $sequence->current_number + 1;
        DB::table('sequences')->where('type', $type)->update([
            'current_number' => $newValue,
            'updated_at' => now(),
        ]);

        return $newValue;
    }

    /**
     * Check if a candidate formatted number is already used in the database.
     */
    public static function isNumberTaken(string $type, string $candidateNumber): bool
    {
        if ($type === 'work_order') {
            if (\Illuminate\Support\Facades\Schema::hasTable('work_orders')) {
                return DB::table('work_orders')->where('number', $candidateNumber)->exists();
            }
        } elseif ($type === 'sale_note' || $type === 'invoice') {
            if (\Illuminate\Support\Facades\Schema::hasTable('sales')) {
                return DB::table('sales')->where('document_number', $candidateNumber)->exists();
            }
        } elseif ($type === 'quote_sequence') {
            if (\Illuminate\Support\Facades\Schema::hasTable('quotes')) {
                return DB::table('quotes')->where('document_number', $candidateNumber)->exists();
            }
        }
        return false;
    }

    /**
     * PREVIEW the next formatted sequence number by type
     */
    public static function previewNumber(string $type, int $startValue = 0): string
    {
        $sequence = DB::table('sequences')->where('type', $type)->first();
        $prefix = $sequence ? $sequence->prefix : null;
        if (!$sequence) {
            $prefix = null;
        }
        $val = $sequence ? (int)$sequence->current_number : $startValue;

        // Auto-heal sequence if it mismatches with DB table max
        $dbMax = self::getDatabaseMaxNumber($type);
        if ($dbMax !== null && $dbMax !== $val) {
            self::updateSequenceByType($type, $dbMax, $sequence != null, $prefix);
            $val = $dbMax;
        }

        $next = $val + 1;
        $candidate = self::formatNumber($next, $prefix);
        while (self::isNumberTaken($type, $candidate)) {
            $next++;
            $candidate = self::formatNumber($next, $prefix);
        }

        return $candidate;
    }

    /**
     * Consume or generate the sequence number safely
     */
    public static function consumeNumber(string $type, ?string $requestedNumber = null): string
    {
        $sequence = DB::table('sequences')->where('type', $type)->lockForUpdate()->first();
        
        $prefix = null;
        if ($sequence) {
            $prefix = $sequence->prefix;
            $current = (int)$sequence->current_number;
        } else {
            $prefix = null;
            $current = 0;
        }

        // Auto-heal sequence if it mismatches with DB table max
        $dbMax = self::getDatabaseMaxNumber($type);
        if ($dbMax !== null && $dbMax !== $current) {
            self::updateSequenceByType($type, $dbMax, $sequence != null, $prefix);
            $current = $dbMax;
        }

        if (!empty($requestedNumber)) {
            // Strip prefix if matches
            $parsedNum = $requestedNumber;
            if ($prefix && str_starts_with(strtoupper($requestedNumber), strtoupper($prefix) . '-')) {
                $parsedNum = substr($requestedNumber, strlen($prefix) + 1);
            }
            
            $requestedInt = (int)$parsedNum;
            
            // Check if the remaining part is a valid integer representation
            if ((string)$requestedInt === $parsedNum || str_pad((string)$requestedInt, strlen($parsedNum), '0', STR_PAD_LEFT) === $parsedNum) {
                if ($requestedInt <= $current || self::isNumberTaken($type, $requestedNumber)) {
                    $next = max($current, $requestedInt) + 1;
                    $candidate = self::formatNumber($next, $prefix);
                    while (self::isNumberTaken($type, $candidate)) {
                        $next++;
                        $candidate = self::formatNumber($next, $prefix);
                    }
                    self::updateSequenceByType($type, $next, $sequence != null, $prefix);
                    return $candidate;
                } else {
                    self::updateSequenceByType($type, $requestedInt, $sequence != null, $prefix);
                    return self::formatNumber($requestedInt, $prefix);
                }
            }
            
            if (!self::isNumberTaken($type, $requestedNumber)) {
                return $requestedNumber;
            }
        }

        $next = $current + 1;
        $candidate = self::formatNumber($next, $prefix);
        while (self::isNumberTaken($type, $candidate)) {
            $next++;
            $candidate = self::formatNumber($next, $prefix);
        }

        self::updateSequenceByType($type, $next, $sequence != null, $prefix);
        return $candidate;
    }

    private static function getDatabaseMaxNumber(string $type): ?int
    {
        try {
            if ($type === 'work_order') {
                if (\Illuminate\Support\Facades\Schema::hasTable('work_orders')) {
                    $numbers = DB::table('work_orders')->whereNull('deleted_at')->pluck('number');
                    $maxVal = 0;
                    foreach ($numbers as $number) {
                        if ($number) {
                            if (preg_match('/(?:OT)-?(\d+)/i', $number, $matches)) {
                                $val = (int)$matches[1];
                                if ($val < 1000000) {
                                    $maxVal = max($maxVal, $val);
                                }
                            } elseif (preg_match('/^\d+$/', $number)) {
                                $val = (int)$number;
                                if ($val < 1000000) {
                                    $maxVal = max($maxVal, $val);
                                }
                            }
                        }
                    }
                    return $maxVal > 0 ? $maxVal : null;
                }
            } elseif ($type === 'sale_note' || $type === 'invoice') {
                if (\Illuminate\Support\Facades\Schema::hasTable('sales')) {
                    $numbers = DB::table('sales')->where('document_type', $type)->pluck('document_number');
                    $maxVal = 0;
                    foreach ($numbers as $number) {
                        $parsedNum = $number;
                        if ($number && preg_match('/^(?:NV|FAC|OT)-?(\d+)$/i', $number, $m)) {
                            $parsedNum = $m[1];
                        }
                        if ($parsedNum && preg_match('/^\d+$/', $parsedNum)) {
                            $val = (int)$parsedNum;
                            if ($val < 1000000) {
                                $maxVal = max($maxVal, $val);
                            }
                        }
                    }
                    return $maxVal > 0 ? $maxVal : null;
                }
            } elseif ($type === 'quote_sequence') {
                if (\Illuminate\Support\Facades\Schema::hasTable('quotes')) {
                    $numbers = DB::table('quotes')->pluck('document_number');
                    $maxVal = 0;
                    foreach ($numbers as $number) {
                        $parsedNum = $number;
                        if ($parsedNum && preg_match('/^\d+$/', $parsedNum)) {
                            $val = (int)$parsedNum;
                            if ($val < 1000000) {
                                $maxVal = max($maxVal, $val);
                            }
                        } else {
                            if ($parsedNum && preg_match('/(\d+)/', $parsedNum, $matches)) {
                                $val = (int)$matches[1];
                                if ($val < 1000000) {
                                    $maxVal = max($maxVal, $val);
                                }
                            }
                        }
                    }
                    return $maxVal > 0 ? $maxVal : null;
                }
            }
        } catch (\Exception $e) {
            logger()->error("Error syncing sequence '{$type}': " . $e->getMessage());
        }
        return null;
    }

    private static function updateSequenceByType(string $type, int $value, bool $exists, ?string $prefix = null)
    {
        if ($exists) {
            DB::table('sequences')->where('type', $type)->update([
                'current_number' => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sequences')->insert([
                'type' => $type,
                'current_number' => $value,
                'prefix' => $prefix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Decrement sequence number safely if it matches the given number
     */
    public static function decrementNumberIfMatches(string $type, string $deletedNumber): void
    {
        $sequence = DB::table('sequences')->where('type', $type)->lockForUpdate()->first();
        if (!$sequence) {
            return;
        }
        
        $prefix = $sequence->prefix;
        $current = (int)$sequence->current_number;
        
        if ($current > 0) {
            $parsedNum = $deletedNumber;
            if ($prefix && str_starts_with(strtoupper($deletedNumber), strtoupper($prefix) . '-')) {
                $parsedNum = substr($deletedNumber, strlen($prefix) + 1);
            }
            
            $deletedInt = (int)$parsedNum;
            if ($deletedInt === $current) {
                DB::table('sequences')->where('type', $type)->update([
                    'current_number' => $current - 1,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /*
     * ==========================================
     * COMPATIBILIDAD CON FLUJOS ANTERIORES
     * ==========================================
     */

    public static function previewNextGlobalNumber(): string
    {
        return self::previewNumber('work_order');
    }

    public static function getNextGlobalNumber(): string
    {
        return self::formatNumber(self::getNextSequenceValue('work_order'), null);
    }

    public static function consumeGlobalNumber(?string $requestedNumber = null): string
    {
        return self::consumeNumber('work_order', $requestedNumber);
    }

    public static function previewNextDirectSaleNumber(): string
    {
        return self::previewNumber('sale_note');
    }

    public static function getNextDirectSaleNumber(): string
    {
        return self::formatNumber(self::getNextSequenceValue('sale_note'), null);
    }

    public static function previewNextQuoteNumber(): string
    {
        return self::previewNumber('quote_sequence');
    }

    public static function getNextQuoteNumber(): string
    {
        return self::formatNumber(self::getNextSequenceValue('quote_sequence'), null);
    }

    public static function consumeQuoteNumber(?string $requestedNumber = null): string
    {
        return self::consumeNumber('quote_sequence', $requestedNumber);
    }

    public static function previewNextWorkOrderNumber(): string
    {
        return self::previewNumber('work_order');
    }

    public static function getNextWorkOrderNumber(): string
    {
        return self::formatNumber(self::getNextSequenceValue('work_order'), null);
    }

    public static function getNextPedidoNumber(): string
    {
        $date = now()->format('Ymd');
        $sequenceName = 'pedidos_sequence_' . $date;
        $val = self::getNextSequenceValue($sequenceName);
        return 'P-' . $date . str_pad((string) $val, 3, '0', STR_PAD_LEFT);
    }

    public static function decrementGlobalNumberIfMatches(string $deletedNumber): void
    {
        $type = 'work_order';
        if (str_starts_with(strtoupper($deletedNumber), 'NV-')) {
            $type = 'sale_note';
        } elseif (str_starts_with(strtoupper($deletedNumber), 'FAC-')) {
            $type = 'invoice';
        }
        
        self::decrementNumberIfMatches($type, $deletedNumber);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDenomination extends Model
{
    protected $fillable = [
        'cash_session_id',
        'bill_100_count',
        'bill_50_count',
        'bill_20_count',
        'bill_10_count',
        'bill_5_count',
        'bill_1_count',
        'coin_1_count',
        'coin_50_count',
        'coin_25_count',
        'coin_10_count',
        'coin_5_count',
        'coin_1_cent_count',
        'total_physical',
        'expected_balance',
        'difference',
    ];

    protected $casts = [
        'total_physical' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });

        static::saving(function ($model) {
            $model->calculatePhysicalTotal();
        });
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function calculatePhysicalTotal()
    {
        // Calcular total físico de billetes
        $billsTotal =
            ($this->bill_100_count * 100) +
            ($this->bill_50_count * 50) +
            ($this->bill_20_count * 20) +
            ($this->bill_10_count * 10) +
            ($this->bill_5_count * 5) +
            ($this->bill_1_count * 1);

        // Calcular total físico de monedas
        $coinsTotal =
            ($this->coin_1_count * 1.00) +
            ($this->coin_50_count * 0.50) +
            ($this->coin_25_count * 0.25) +
            ($this->coin_10_count * 0.10) +
            ($this->coin_5_count * 0.05) +
            ($this->coin_1_cent_count * 0.01);

        $this->total_physical = $billsTotal + $coinsTotal;

        // Calcular diferencia con el balance esperado
        $this->difference = $this->total_physical - $this->expected_balance;
    }

    public function getDenominationsArray(): array
    {
        return [
            'bills' => [
                ['value' => 100, 'count' => $this->bill_100_count, 'total' => $this->bill_100_count * 100],
                ['value' => 50, 'count' => $this->bill_50_count, 'total' => $this->bill_50_count * 50],
                ['value' => 20, 'count' => $this->bill_20_count, 'total' => $this->bill_20_count * 20],
                ['value' => 10, 'count' => $this->bill_10_count, 'total' => $this->bill_10_count * 10],
                ['value' => 5, 'count' => $this->bill_5_count, 'total' => $this->bill_5_count * 5],
                ['value' => 1, 'count' => $this->bill_1_count, 'total' => $this->bill_1_count * 1],
            ],
            'coins' => [
                ['value' => 1.00, 'count' => $this->coin_1_count, 'total' => $this->coin_1_count * 1.00],
                ['value' => 0.50, 'count' => $this->coin_50_count, 'total' => $this->coin_50_count * 0.50],
                ['value' => 0.25, 'count' => $this->coin_25_count, 'total' => $this->coin_25_count * 0.25],
                ['value' => 0.10, 'count' => $this->coin_10_count, 'total' => $this->coin_10_count * 0.10],
                ['value' => 0.05, 'count' => $this->coin_5_count, 'total' => $this->coin_5_count * 0.05],
                ['value' => 0.01, 'count' => $this->coin_1_cent_count, 'total' => $this->coin_1_cent_count * 0.01],
            ]
        ];
    }

    public function getFormattedTotalPhysicalAttribute(): string
    {
        return '$' . number_format($this->total_physical, 2);
    }

    public function getFormattedExpectedBalanceAttribute(): string
    {
        return '$' . number_format($this->expected_balance, 2);
    }

    public function getFormattedDifferenceAttribute(): string
    {
        $diff = abs($this->difference);
        $prefix = $this->difference >= 0 ? '+' : '-';
        return $prefix . ' $' . number_format($diff, 2);
    }

    public function getBalanceStatusAttribute(): string
    {
        if (abs($this->difference) < 0.01) {
            return 'balanced';
        } elseif ($this->difference > 0) {
            return 'surplus';
        } else {
            return 'shortage';
        }
    }

    public function getBalanceStatusDescriptionAttribute(): string
    {
        return match ($this->balance_status) {
            'balanced' => 'Balanceado',
            'surplus' => 'Sobrante',
            'shortage' => 'Faltante',
            default => 'Desconocido',
        };
    }
}

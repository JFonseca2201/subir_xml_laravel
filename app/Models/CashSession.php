<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSession extends Model
{
    protected $fillable = [
        'user_id',
        'opening_balance',
        'cash_deposits',
        'cash_withdrawals',
        'sales_total',
        'status',
        'final_balance',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'cash_deposits' => 'decimal:2',
        'cash_withdrawals' => 'decimal:2',
        'sales_total' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'closed_at' => 'datetime',
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
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashDenominations(): HasMany
    {
        return $this->hasMany(CashDenomination::class);
    }

    public function getExpectedBalanceAttribute(): float
    {
        return $this->opening_balance + $this->cash_deposits + $this->sales_total - $this->cash_withdrawals;
    }

    public function getFormattedOpeningBalanceAttribute(): string
    {
        return '$' . number_format($this->opening_balance, 2);
    }

    public function getFormattedCashDepositsAttribute(): string
    {
        return '$' . number_format($this->cash_deposits, 2);
    }

    public function getFormattedCashWithdrawalsAttribute(): string
    {
        return '$' . number_format($this->cash_withdrawals, 2);
    }

    public function getFormattedSalesTotalAttribute(): string
    {
        return '$' . number_format($this->sales_total, 2);
    }

    public function getFormattedExpectedBalanceAttribute(): string
    {
        return '$' . number_format($this->getExpectedBalanceAttribute(), 2);
    }

    public function getFormattedFinalBalanceAttribute(): string
    {
        return '$' . number_format($this->final_balance ?? 0, 2);
    }

    public function getStatusDescriptionAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Abierta',
            'closed' => 'Cerrada',
            default => 'Desconocido',
        };
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}

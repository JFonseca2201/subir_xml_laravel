<?php

namespace App\Models\Finance;

use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\RecordsFinancialMovements;
use Carbon\Carbon;

class FinanceRecord extends Model

{
    use HasFactory;
    use RecordsFinancialMovements;

    protected $table = 'finance_records';
    // Constants
    const TYPE_INCOME = 0;
    const TYPE_EXPENSE = 1;

    const ACCOUNT_CASH = 1;
    const ACCOUNT_PICHINCHA = 2;
    const ACCOUNT_GUAYAQUIL = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entry_date',
        'type',
        'account_id',
        'payment_method',
        'amount',
        'work_order_number',
        'invoice_number',
        'description',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'type' => 'integer',
        'account_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Get user that owns finance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get account that owns finance record.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class , 'account_id');
    }

    /**
     * Get payment distributions for this finance record.
     */
    public function paymentDistributions()
    {
        return $this->hasMany(PaymentDistribution::class);
    }

    /**
     * Boot model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default entry_date to current date in Ecuador timezone
        static::creating(function ($record) {
            if (empty($record->entry_date)) {
                $record->entry_date = now('America/Guayaquil')->toDateString();
            }

            // Set authenticated user
            if (empty($record->user_id)) {
                $record->user_id = 1; // Default user
            }

            // Apply payment method logic based on account
            $record->applyPaymentMethodRules();
        });

        static::updating(function ($record) {
            $record->applyPaymentMethodRules();
        });
    }

    /**
     * Apply payment method rules based on account_id
     */
    public function applyPaymentMethodRules(): void
    {
        if ($this->account_id == self::ACCOUNT_CASH) {
            $this->payment_method = 'cash';
        }
        elseif (in_array($this->account_id, [self::ACCOUNT_PICHINCHA, self::ACCOUNT_GUAYAQUIL])) {
            $this->payment_method = 'transfer';
        }
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_INCOME ? 'Ingreso' : 'Egreso';
    }

    /**
     * Get account label.
     */
    public function getAccountLabelAttribute(): string
    {
        return match ($this->account_id) {
                self::ACCOUNT_CASH => 'Efectivo/Caja chica',
                self::ACCOUNT_PICHINCHA => 'Banco Pichincha',
                self::ACCOUNT_GUAYAQUIL => 'Banco Guayaquil',
                default => 'Desconocida',
            };
    }

    /**
     * Get payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method === 'cash' ? 'Efectivo' : 'Transferencia';
    }

    /**
     * Scope to get only income records.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope to get only expense records.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Scope to get records by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('entry_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope to get records for specific account.
     */
    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}
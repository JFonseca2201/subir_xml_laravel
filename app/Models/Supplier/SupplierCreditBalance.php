<?php

namespace App\Models\Supplier;

use App\Models\Finance\Account;
use App\Models\Finance\FinanceRecord;
use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierCreditBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'supplier_credit_balances';

    protected $fillable = [
        'supplier_id',
        'account_id',
        'finance_record_id',
        'source_type',
        'reference_number',
        'total_payment_amount',
        'invoices_total_amount',
        'amount',
        'used_amount',
        'remaining_balance',
        'status',
        'resolution_type',
        'notes',
    ];

    protected $casts = [
        'total_payment_amount' => 'decimal:2',
        'invoices_total_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function financeRecord()
    {
        return $this->belongsTo(FinanceRecord::class, 'finance_record_id');
    }

    public function usages()
    {
        return $this->hasMany(SupplierCreditUsage::class, 'supplier_credit_balance_id');
    }

    /**
     * Aplica un monto de saldo a favor a una compra.
     */
    public function applyAmount(float $amountToApply, ?int $invoiceId = null, ?string $notes = null): SupplierCreditUsage
    {
        if ($amountToApply <= 0) {
            throw new \InvalidArgumentException('El monto a aplicar debe ser mayor a 0');
        }

        if ($amountToApply > (float) $this->remaining_balance + 0.001) {
            throw new \InvalidArgumentException('El monto a aplicar excede el saldo disponible');
        }

        $newUsed = (float) $this->used_amount + $amountToApply;
        $newRemaining = max(0, (float) $this->amount - $newUsed);

        $newStatus = $newRemaining <= 0.001 ? 'fully_used' : 'partially_used';

        $this->update([
            'used_amount' => $newUsed,
            'remaining_balance' => $newRemaining,
            'status' => $newStatus,
        ]);

        return $this->usages()->create([
            'invoice_id' => $invoiceId,
            'amount_applied' => $amountToApply,
            'notes' => $notes,
        ]);
    }
}

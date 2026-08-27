<?php

namespace App\Models\Supplier;

use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierCreditUsage extends Model
{
    use HasFactory;

    protected $table = 'supplier_credit_usages';

    protected $fillable = [
        'supplier_credit_balance_id',
        'invoice_id',
        'amount_applied',
        'notes',
    ];

    protected $casts = [
        'amount_applied' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creditBalance()
    {
        return $this->belongsTo(SupplierCreditBalance::class, 'supplier_credit_balance_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}

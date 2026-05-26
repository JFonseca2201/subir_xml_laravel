<?php

namespace App\Models\Finance;

use App\Models\Supplier\Supplier;
use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayable extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'invoice_id',
        'total_amount',
        'amount_paid',
        'status',
        'due_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

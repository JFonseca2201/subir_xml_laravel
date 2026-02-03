<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'code',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'total'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
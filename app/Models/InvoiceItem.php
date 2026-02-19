<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'subtotal',
        'tax',
        'total',
        'item_type',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class , 'invoice_id');
    }
}

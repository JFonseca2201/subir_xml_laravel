<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Model;
use App\Models\Supplier;
use App\Models\InvoiceItem;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'supplier_id',
        'access_key',
        'invoice_number',
        'issue_date',
        'subtotal',
        'tax',
        'total'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
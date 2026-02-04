<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Model;
use App\Models\Supplier;
use App\Models\InvoiceItem;
use Carbon\Carbon;

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

    public function invoices_items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeFilterAdvance($query, $search, $start_date, $end_date, $type, $supplier)
    {
        if ($search) {
            $query->where('id', $search)->orWhere('invoice_number', 'LIKE', "%{$search}%");
        }

        if ($start_date && $end_date) {
                $query->whereBetween('created_at', [
                Carbon::parse($start_date)->format('Y-m-d').' 00:00:00',
                Carbon::parse($end_date)->format('Y-m-d').' 23:59:59',
            ]);
                }
        if ($type) {
            $query->where('type', $type);
        }
        if ($supplier) {
            $query->where('supplier_id', $supplier);
        }
        
        return $query;

    }
}
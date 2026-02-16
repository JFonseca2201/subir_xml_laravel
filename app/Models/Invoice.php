<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'supplier_id',
        'access_key',
        'invoice_number',
        'issue_date',
        'discount',
        'subtotal',
        'tax',
        'total',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices_items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeFilterAdvance($query, $search, $start_date, $end_date, $supplier)
    {
        if ($search) {
            $query->where('invoice_number', 'LIKE', "%{$search}%")
                ->orWhere('id', $search)
                ->orWhereHas('invoices_items', function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
        }

        if ($start_date && $end_date) {
            $start_date = trim($start_date);
            $end_date = trim($end_date);
            $query->whereBetween('issue_date', [
                Carbon::parse($start_date)->format('Y-m-d'),
                Carbon::parse($end_date)->format('Y-m-d'),
            ]);
        }

        if ($supplier) {
            $query->where('supplier_id', $supplier);
        }

        return $query;
    }
}

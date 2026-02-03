<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'tax_id',
        'name',
        'address'
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
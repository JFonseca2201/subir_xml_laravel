<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParallelTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'quantity',
        'unit_cost',
        'cost',
        'unit',
        'amount',
        'account',
        'date',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'quantity' => 'integer',
        'unit_cost' => 'float',
        'cost' => 'float',
        'amount' => 'float',
        'date' => 'date:Y-m-d',
    ];

    /**
     * Get the user that registered the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

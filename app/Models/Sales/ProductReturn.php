<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Traits\RecordsFinancialMovements;

class ProductReturn extends Model
{
    use HasFactory, SoftDeletes, RecordsFinancialMovements;

    protected $fillable = [
        'return_number',
        'sale_id',
        'user_id',
        'type',
        'refund_amount',
        'reason',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductReturnDetail::class);
    }
}

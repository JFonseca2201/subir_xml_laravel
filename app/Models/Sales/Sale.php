<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Client\Client;
use App\Models\Vehicles\Vehicle;
use App\Models\User;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type', 'document_number', 'client_id', 'vehicle_id', 'user_id',
        'mileage', 'service_date', 'subtotal', 'tax_amount', 'total',
        'status', 'payment_status', 'is_credited', 'payment_method', 'observations'
    ];

    protected $casts = [
        'service_date' => 'date',
    ];

    /**
     * Una venta tiene muchos detalles.
     */
    public function details(): HasMany
    {
        // Apuntamos al modelo hermano dentro de la misma carpeta sales
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
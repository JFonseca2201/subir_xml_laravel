<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\SerializeDateLocal;

class Quote extends Model
{
    use HasFactory, SerializeDateLocal;

    protected $fillable = [
        'document_number',
        'client_id',
        'vehicle_id',
        'work_order_id',
        'mileage',
        'service_date',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'observations',
        'user_id',
        'converted_sale_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    /**
     * Accessor para determinar si la cotización ya fue convertida.
     */
    public function getIsConvertedAttribute(): bool
    {
        return !is_null($this->converted_sale_id);
    }

    /**
     * Relación: detalles de la cotización.
     */
    public function details()
    {
        return $this->hasMany(QuoteDetail::class);
    }

    /**
     * Relación: cliente asociado.
     */
    public function client()
    {
        return $this->belongsTo(\App\Models\Client\Client::class);
    }

    /**
     * Relación: vehículo asociado (opcional).
     */
    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Vehicles\Vehicle::class);
    }

    /**
     * Relación: usuario creador.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relación: orden de trabajo de origen (opcional).
     */
    public function workOrder()
    {
        return $this->belongsTo(\App\Models\WorkOrder\WorkOrder::class, 'work_order_id');
    }

    /**
     * Relación: técnicos asignados.
     */
    public function technicians()
    {
        return $this->belongsToMany(\App\Models\Employee\Employee::class, 'quote_technicians');
    }

    /**
     * Relación: la venta/factura en la que se convirtió (opcional).
     */
    public function convertedSale()
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }
}

<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sales\SaleDetail;
use App\Traits\RecordsFinancialMovements;

class Sale extends Model
{
    use HasFactory, RecordsFinancialMovements;

    protected $fillable = [
        'document_type',
        'document_number',
        'client_id',
        'vehicle_id',
        'work_order_id',
        'mileage',
        'service_date',
        'subtotal',
        'tax_amount',
        'total',
        'payment_status',
        'is_credited',
        'payment_method',
        'observations',
        'user_id'
    ];

    protected $casts = [
        'service_date' => 'date',
        'is_credited' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Una venta tiene muchos detalles (items).
     */
    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * Una venta pertenece a un cliente.
     */
    public function client()
    {
        return $this->belongsTo(\App\Models\Client\Client::class);
    }

    /**
     * Una venta pertenece a un vehículo (opcional).
     */
    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Vehicles\Vehicle::class);
    }

    /**
     * Una venta pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Una venta puede tener un registro financiero asociado.
     */
    public function financeRecord()
    {
        return $this->hasOne(\App\Models\Finance\FinanceRecord::class, 'invoice_number', 'document_number');
    }

    /**
     * Una venta puede pertenecer a una orden de trabajo.
     */
    public function workOrder()
    {
        return $this->belongsTo(\App\Models\WorkOrder\WorkOrder::class, 'work_order_id');
    }
}

<?php

namespace App\Models\WorkOrder;

use App\Models\Employee\Employee;

use App\Models\Client\Client;
use App\Models\Sales\Sale;
use App\Models\Vehicles\Vehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\SerializeDateLocal;
use App\Traits\HasAttachments;

class WorkOrder extends Model
{
    use SoftDeletes, SerializeDateLocal, HasAttachments;

    protected $fillable = [
        'number',
        'date',
        'client_id',
        'vehicle_id',
        'user_id',
        'mileage',
        'fuel_level',
        'observations',
        'status',
    ];

    protected $casts = [
        'mileage' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];



    /**
     * Una orden de trabajo pertenece a un cliente.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Una orden de trabajo pertenece a un vehículo (opcional).
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Una orden de trabajo pertenece a un usuario (mecánico o recepcionista).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Una orden de trabajo puede tener una venta asociada (factura o nota de venta).
     * Se excluyen cotizaciones para que no bloqueen la facturación posterior.
     */
    public function sale()
    {
        return $this->hasOne(Sale::class, 'work_order_id')->where('document_type', '!=', 'quote');
    }

    /**
     * Cotización origen desde la cual se generó esta orden de trabajo (si aplica).
     */
    public function quote()
    {
        return $this->hasOne(Sale::class, 'work_order_id')->where('document_type', 'quote');
    }

    /**
     * Una orden de trabajo tiene muchos items (productos/servicios).
     */
    public function items()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    /**
     * Una orden de trabajo tiene muchos técnicos (empleados).
     */
    public function technicians()
    {
        return $this->belongsToMany(Employee::class, 'work_order_technicians');
    }
}

<?php

namespace App\Models;

use App\Models\Client\Client;
use App\Models\Sales\Sale;
use App\Models\Vehicles\Vehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'client_id',
        'vehicle_id',
        'user_id',
        'mileage',
        'fuel_level',
        'observations',
        'diagnostic',
        'status',
    ];

    protected $casts = [
        'mileage' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['created_at'] = Carbon::now();
    }

    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['updated_at'] = Carbon::now();
    }

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
     * Una orden de trabajo puede tener una venta asociada.
     */
    public function sale()
    {
        return $this->hasOne(Sale::class, 'work_order_id');
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

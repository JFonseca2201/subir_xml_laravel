<?php

namespace App\Models;

use App\Models\Client\Client;
use App\Models\Vehicles\Vehicle;
use App\Models\WorkOrder\WorkOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceReminder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'vehicle_id',
        'work_order_id',
        'service_category',
        'interval_km',
        'last_service_mileage',
        'target_mileage',
        'avg_daily_km',
        'last_service_date',
        'scheduled_date',
        'title',
        'description',
        'status',
        'notified_at',
        'notification_channel',
    ];

    protected $casts = [
        'last_service_mileage' => 'integer',
        'target_mileage'       => 'integer',
        'interval_km'          => 'integer',
        'avg_daily_km'         => 'decimal:2',
        'last_service_date'    => 'date:Y-m-d',
        'scheduled_date'       => 'date:Y-m-d',
        'notified_at'          => 'datetime',
        'created_at'           => 'datetime:Y-m-d H:i:s',
        'updated_at'           => 'datetime:Y-m-d H:i:s',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }
}

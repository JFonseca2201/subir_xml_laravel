<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Client\Client;
use App\Models\Vehicles\Vehicle;

class WorkOrder extends Model
{
    protected $fillable = [
        'number',
        'description',
        'status',
        'client_id',
        'vehicle_id',
        'employee_id',
        'total_amount',
        'start_date',
        'end_date'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

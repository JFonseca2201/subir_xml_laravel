<?php

namespace App\Models\Client;

use App\Models\Config\Sucursale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'surname',
        'full_name',
        'phone',
        'email',
        'type_client',
        'type_document',
        'n_document',
        'birth_date',
        'user_id',
        'sucursale_id', //por defecto es 1
        'state',
        'gender',
        'ubigeo_region',
        'ubigeo_provincia',
        'ubigeo_distrito',
        'region',
        'provincia',
        'distrito',
        'address',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }
}

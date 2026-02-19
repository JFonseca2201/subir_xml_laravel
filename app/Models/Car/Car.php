<?php

namespace App\Models\Car;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'brand',
        'model',
        'year',
        'color',
        'placa',
    ];
}

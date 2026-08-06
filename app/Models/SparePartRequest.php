<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SparePartRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand',
        'model',
        'year',
        'traction',
        'origin_country',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

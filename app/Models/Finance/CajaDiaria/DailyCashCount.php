<?php

namespace App\Models\Finance\CajaDiaria;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DailyCashCount extends Model
{
    protected $fillable = [
        'count_date',
        'cash_total',
        'pichincha_total',
        'guayaquil_total',
        'grand_total',
        'cash_details',
        'observations',
        'is_sealed',
        'user_id'
    ];

    // Convertimos el JSON de la BD en un array manipulable en Vue 3
    protected $casts = [
        'cash_details' => 'array',
        'count_date' => 'date',
        'is_sealed' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
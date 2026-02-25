<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['name', 'identification', 'phone', 'email', 'address'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });
    }

    public function contributions()
    {
        return $this->hasMany(PartnerContribution::class);
    }

    public function getTotalContributionsAttribute()
    {
        return $this->contributions()->sum('amount');
    }

    public function getFormattedTotalContributionsAttribute()
    {
        return '$' . number_format($this->total_contributions, 2);
    }
}

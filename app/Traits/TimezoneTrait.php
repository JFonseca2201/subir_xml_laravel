<?php

namespace App\Traits;

trait TimezoneTrait
{
    protected static function bootTimezoneTrait()
    {
        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });
    }
}

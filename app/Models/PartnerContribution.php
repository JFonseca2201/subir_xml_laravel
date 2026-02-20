<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerContribution extends Model
{
    protected $fillable = ['partner_id', 'amount', 'account_id', 'contribution_date', 'notes'];

    protected $casts = [
        'contribution_date' => 'date:Y-m-d',
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

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}

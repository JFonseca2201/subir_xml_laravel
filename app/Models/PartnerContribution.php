<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerContribution extends Model
{
    protected $fillable = [
        'partner_id',
        'amount',
        'account_id',
        'contribution_date',
        'notes',
    ];

    protected $casts = [
        'contribution_date' => 'date',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}

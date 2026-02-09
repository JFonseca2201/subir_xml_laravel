<?php

// app/Models/Partner.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'identification',
        'phone',
        'email',
    ];

    public function contributions()
    {
        return $this->hasMany(PartnerContribution::class);
    }
}
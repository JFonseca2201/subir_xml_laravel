<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'default_price'];
}

<?php

namespace App\Models\Partner;

use App\Models\Finance\Account;

use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\RecordsFinancialMovements;

class AporteCapital extends Model
{
    use HasFactory, SoftDeletes;
    use RecordsFinancialMovements;

    protected $table = 'aportes_capital';

    protected $fillable = [
        'partner_id',
        'user_id',
        'monto',
        'descripcion',
        'cuenta_id',
        'metodo_pago',
        'fecha_aporte',
        'hora_aporte',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_aporte' => 'date',
        'hora_aporte' => 'datetime:H:i:s',
    ];

    // Relaciones
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cuenta()
    {
        return $this->belongsTo(Account::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoCuenta::class, 'referencia_id')
            ->where('referencia', 'aporte_capital');
    }

    // Scopes útiles
    public function scopeHoy($query)
    {
        return $query->whereDate('fecha_aporte', today());
    }

    public function scopeEsteMes($query)
    {
        return $query->whereMonth('fecha_aporte', now()->month)
            ->whereYear('fecha_aporte', now()->year);
    }

    public function scopePorFecha($query)
    {
        return $query->orderBy('fecha_aporte', 'desc')
            ->orderBy('hora_aporte', 'desc');
    }
}
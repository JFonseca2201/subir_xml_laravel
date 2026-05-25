<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoCuenta extends Model

{
    use HasFactory;

    protected $table = 'movimientos_cuentas';

    protected $fillable = [
        'cuenta_id',
        'tipo',
        'monto',
        'descripcion',
        'referencia',
        'referencia_id',
        'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
        'referencia_id' => 'integer',
    ];

    // Relaciones
    public function cuenta()
    {
        return $this->belongsTo(Account::class);
    }

    // Scopes útiles
    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'INGRESO');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'EGRESO');
    }

    public function scopePorFecha($query)
    {
        return $query->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function scopeDeReferencia($query, $referencia, $referenciaId = null)
    {
        $query->where('referencia', $referencia);

        if ($referenciaId) {
            $query->where('referencia_id', $referenciaId);
        }

        return $query;
    }
}

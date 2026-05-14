<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AporteCapital;
use App\Models\MovimientoCuenta;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class AporteController extends Controller
{
    /**
     * Listar aportes agrupados por fecha
     */
    public function index(Request $request)
    {
        $aportes = AporteCapital::with(['partner', 'user', 'cuenta'])
            ->whereNull('deleted_at')
            ->orderBy('fecha_aporte', 'desc')
            ->orderBy('hora_aporte', 'desc')
            ->get();

        // Agrupar por fecha
        $agrupados = $aportes->groupBy(function ($aporte) {
            $fecha = Carbon::parse($aporte->fecha_aporte);

            if ($fecha->isToday()) {
                return 'Hoy';
            } elseif ($fecha->isYesterday()) {
                return 'Ayer';
            } elseif ($fecha->year === now()->year && $fecha->month === now()->month && $fecha->day === now()->subDay(2)->day) {
                return $fecha->locale('es')->translatedFormat('l d F');
            } else {
                return $fecha->locale('es')->translatedFormat('l d F');
            }
        });

        // Formatear datos
        $data = [];
        $totalHoy = 0;
        $totalMes = 0;
        $totalGeneral = 0;

        foreach ($agrupados as $fecha => $aportesDia) {
            $totalDia = $aportesDia->sum('monto');

            $aportesFormateados = $aportesDia->map(function ($aporte) {
                return [
                    'id' => $aporte->id,
                    'partner_nombre' => $aporte->partner->name ?? 'N/A',
                    'partner_id' => $aporte->partner_id,
                    'monto' => (float) $aporte->monto,
                    'descripcion' => $aporte->descripcion,
                    'cuenta' => $aporte->cuenta->name ?? 'N/A',
                    'cuenta_id' => $aporte->cuenta_id,
                    'metodo_pago' => $aporte->metodo_pago,
                    'fecha_aporte' => $aporte->fecha_aporte,
                    'hora_aporte' => Carbon::parse($aporte->hora_aporte)->format('H:i'),
                    'hora' => Carbon::parse($aporte->hora_aporte)->format('H:i'),
                    'user_nombre' => $aporte->user->name ?? 'N/A',
                ];
            });

            $data[] = [
                'fecha' => $aportesDia->first()->fecha_aporte,
                'label' => $fecha,
                'total_dia' => (float) $totalDia,
                'aportes' => $aportesFormateados,
            ];

            // Acumular totales
            $totalGeneral += $totalDia;

            if ($fecha === 'Hoy') {
                $totalHoy += $totalDia;
            }

            if (
                Carbon::parse($aportesDia->first()->fecha_aporte)->month === now()->month &&
                Carbon::parse($aportesDia->first()->fecha_aporte)->year === now()->year
            ) {
                $totalMes += $totalDia;
            }
        }

        return response()->json([
            'resumen' => [
                'total_hoy' => (float) $totalHoy,
                'total_mes' => (float) $totalMes,
                'total_general' => (float) $totalGeneral,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Crear aporte con impacto contable
     */
    public function store(Request $request)
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
            'cuenta_id' => 'required|exists:accounts,id',
            'metodo_pago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'fecha_aporte' => 'required|date|before_or_equal:today',
            'hora_aporte' => 'required|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();

            // Crear aporte
            $aporte = AporteCapital::create([
                'partner_id' => $request->partner_id,
                'user_id' => auth()->id(),
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'cuenta_id' => $request->cuenta_id,
                'metodo_pago' => $request->metodo_pago,
                'fecha_aporte' => $request->fecha_aporte,
                'hora_aporte' => $request->hora_aporte,
            ]);

            // Crear movimiento contable
            MovimientoCuenta::create([
                'cuenta_id' => $request->cuenta_id,
                'tipo' => 'INGRESO',
                'monto' => $request->monto,
                'descripcion' => "Aporte de capital: {$request->descripcion}",
                'referencia' => 'aporte_capital',
                'referencia_id' => $aporte->id,
                'fecha' => $request->fecha_aporte,
            ]);

            $aporte->registerMovement(
            $request->cuenta_id,
            'income',
            $request->monto,
            "Aporte de socio: {$aporte->partner->nombre_completo}", // O el nombre del socio
            $request->fecha_aporte,
            ['metodo' => $request->metodo_pago] // Metadata opcional
            );

            // Actualizar saldo de la cuenta
            $cuenta = Account::findOrFail($request->cuenta_id);
            $cuenta->increment('saldo_actual', $request->monto);

            DB::commit();

            return response()->json([
                'message' => 'Aporte creado exitosamente',
                'aporte' => $aporte->load(['partner', 'user', 'cuenta']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete con reverso contable
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // 1. Buscamos el aporte
            $aporte = AporteCapital::findOrFail($id);

            // 2. Crear movimiento inverso en MovimientoCuenta (USAR DATOS ANTES DE BORRAR)
            MovimientoCuenta::create([
                'cuenta_id' => $aporte->cuenta_id,
                'tipo' => 'EGRESO',
                'monto' => $aporte->monto,
                'descripcion' => "Reverso de aporte: {$aporte->descripcion}",
                'referencia' => 'reverso_aporte',
                'referencia_id' => $aporte->id,
                'fecha' => now()->toDateString(),
            ]);

            // 3. Restar monto del saldo de la cuenta
            $cuenta = Account::findOrFail($aporte->cuenta_id);
            $cuenta->decrement('saldo_actual', $aporte->monto);

            // 4. ELIMINAR DEL DASHBOARD DE OPERACIONES
            // Usamos la relación del Trait para quitarlo de la vista unificada
            if ($aporte->financialMovement()) {
                $aporte->financialMovement()->delete();
            }

            // 5. Soft delete del aporte (AL FINAL)
            // Lo hacemos al final para asegurar que todas las referencias anteriores funcionen
            $aporte->delete();

            DB::commit();

            return response()->json([
                'message' => 'Aporte eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar aporte con recalculo de saldos
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
            'cuenta_id' => 'required|exists:accounts,id',
            'metodo_pago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'fecha_aporte' => 'required|date|before_or_equal:today',
            'hora_aporte' => 'required|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();

            $aporte = AporteCapital::findOrFail($id);
            $cuentaAnterior = $aporte->cuenta_id;
            $montoAnterior = $aporte->monto;

            // Si cambió la cuenta o el monto, revertir el movimiento anterior
            if ($cuentaAnterior != $request->cuenta_id || $montoAnterior != $request->monto) {
                // Revertir movimiento anterior
                MovimientoCuenta::create([
                    'cuenta_id' => $cuentaAnterior,
                    'tipo' => 'EGRESO',
                    'monto' => $montoAnterior,
                    'descripcion' => "Reverso por edición de aporte: {$aporte->descripcion}",
                    'referencia' => 'reverso_edicion_aporte',
                    'referencia_id' => $aporte->id,
                    'fecha' => now()->toDateString(),
                ]);

                // Restar saldo anterior
                $cuentaAnt = Account::findOrFail($cuentaAnterior);
                $cuentaAnt->decrement('saldo_actual', $montoAnterior);

                // Aplicar nuevo movimiento
                MovimientoCuenta::create([
                    'cuenta_id' => $request->cuenta_id,
                    'tipo' => 'INGRESO',
                    'monto' => $request->monto,
                    'descripcion' => "Aporte de capital (editado): {$request->descripcion}",
                    'referencia' => 'aporte_capital',
                    'referencia_id' => $aporte->id,
                    'fecha' => $request->fecha_aporte,
                ]);

                // Sumar nuevo saldo
                $cuentaNueva = Account::findOrFail($request->cuenta_id);
                $cuentaNueva->increment('saldo_actual', $request->monto);
            }

            // Actualizar datos del aporte
            $aporte->update([
                'partner_id' => $request->partner_id,
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'cuenta_id' => $request->cuenta_id,
                'metodo_pago' => $request->metodo_pago,
                'fecha_aporte' => $request->fecha_aporte,
                'hora_aporte' => $request->hora_aporte,
            ]);
            $aporte->registerMovement(
            $request->cuenta_id,
            'income',
            $request->monto,
            "Aporte de capital (editado): {$request->descripcion}",
            $request->fecha_aporte,
            ['metodo' => $request->metodo_pago, 'edit_log' => 'editado_manualmente']
        );

            DB::commit();

            return response()->json([
                'message' => 'Aporte actualizado exitosamente',
                'aporte' => $aporte->load(['partner', 'user', 'cuenta']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
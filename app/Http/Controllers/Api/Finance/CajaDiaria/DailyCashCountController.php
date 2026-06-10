<?php

namespace App\Http\Controllers\Api\Finance\CajaDiaria;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\CajaDiaria\DailyCashCount;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyCashCountController extends Controller
{
    /**
     * Obtener el estado del día actual y el arrastre del día anterior
     */
    public function getStatusByDate(Request $request)
    {
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);

        // 1. Buscamos si ya se registró el conteo de esta fecha
        $currentCount = DailyCashCount::where('count_date', $date)->first();

        // 2. Buscamos el conteo inmediatamente anterior para heredar los saldos iniciales
        $previousCount = DailyCashCount::where('count_date', '<', $date)
            ->orderBy('count_date', 'desc')
            ->first();

        // Traer los balances teóricos actuales de las cuentas correspondientes
        $saldoCajaChica = DB::table('accounts')->where('id', 1)->value('saldo_actual');
        $saldoPichincha = DB::table('accounts')->where('id', 2)->value('saldo_actual');
        $saldoGuayaquil = DB::table('accounts')->where('id', 3)->value('saldo_actual');

        return response()->json([
            'success' => true,
            'date_formatted' => $carbonDate->isoFormat('dddd DD [de] MMMM YYYY'),
            'already_counted' => !is_null($currentCount),
            'is_sealed' => $currentCount ? (bool)$currentCount->is_sealed : false,
            'current_data' => $currentCount,
            'initial_balances' => $previousCount ? [
                'cash' => $previousCount->cash_total,
                'pichincha' => $previousCount->pichincha_total,
                'guayaquil' => $previousCount->guayaquil_total,
                'total' => $previousCount->grand_total,
                'origin_date' => $previousCount->count_date->format('Y-m-d')
            ] : [
                'cash' => 0,
                'pichincha' => 0,
                'guayaquil' => 0,
                'total' => 0,
                'origin_date' => null
            ],
            // 🛠️ CORRECCIÓN DEFINITIVA: Limpiamos las flechas para usar el valor numérico directo
            'system_balances' => [
                'cash' => $saldoCajaChica ? (float)$saldoCajaChica : 0.00,
                'pichincha' => $saldoPichincha ? (float)$saldoPichincha : 0.00,
                'guayaquil' => $saldoGuayaquil ? (float)$saldoGuayaquil : 0.00,
            ]
        ]);
    }

    /**
     * Guardar o actualizar el arqueo del día
     */
    public function store(Request $request)
    {
        $request->validate([
            'count_date'      => 'required|date',
            'pichincha_total' => 'required|numeric|min:0',
            'guayaquil_total' => 'required|numeric|min:0',
            'cash_details'    => 'required|array', // El desglose de billetes de la interfaz
            'observations'    => 'nullable|string',
        ]);

        $existingCount = DailyCashCount::where('count_date', $request->count_date)->first();
        if ($existingCount && $existingCount->is_sealed) {
            return response()->json([
                'success' => false,
                'message' => 'El día ya está sellado y no puede ser modificado.'
            ], 422);
        }

        // Estructura esperada del desglose de efectivo desde Vue 3:
        // bills: { '100': 2, '20': 5, ... }, coins: { '1': 4, '0.50': 10, ... }
        $details = $request->cash_details;

        // Calculamos el total de efectivo de forma matemática estricta en el backend
        $cashTotal = 0;
        if (isset($details['bills'])) {
            foreach ($details['bills'] as $denomination => $quantity) {
                $cashTotal += ((float)$denomination * (int)$quantity);
            }
        }
        if (isset($details['coins'])) {
            foreach ($details['coins'] as $denomination => $quantity) {
                $cashTotal += ((float)$denomination * (int)$quantity);
            }
        }

        $grandTotal = $cashTotal + $request->pichincha_total + $request->guayaquil_total;

        // Guardamos usando updateOrCreate por si necesitan corregir un error el mismo día
        $count = DailyCashCount::updateOrCreate(
            ['count_date' => $request->count_date],
            [
                'cash_total'      => $cashTotal,
                'pichincha_total' => $request->pichincha_total,
                'guayaquil_total' => $request->guayaquil_total,
                'grand_total'     => $grandTotal,
                'cash_details'    => $details,
                'observations'    => $request->observations,
                'user_id'         => auth()->id() ?? $request->user_id ?? 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cuadrante de caja diario guardado con éxito!.',
            'data' => $count
        ]);
    }

    /**
     * Sellar el día para evitar modificaciones
     */
    public function seal(Request $request)
    {
        $request->validate([
            'count_date' => 'required|date',
        ]);

        $count = DailyCashCount::where('count_date', $request->count_date)->first();

        if (!$count) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede sellar el día porque no existe un arqueo guardado.'
            ], 404);
        }

        if ($count->is_sealed) {
            return response()->json([
                'success' => false,
                'message' => 'El día ya se encuentra sellado.'
            ], 422);
        }

        $count->update(['is_sealed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Día sellado correctamente. Ya no se permiten modificaciones.'
        ]);
    }
}
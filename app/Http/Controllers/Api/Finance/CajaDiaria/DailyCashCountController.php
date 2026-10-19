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

        // 3. Buscamos el último conteo absoluto registrado en el sistema
        $latestOverallCount = DailyCashCount::with('user:id,name')
            ->orderBy('count_date', 'desc')
            ->first();

        // Traer los balances teóricos actuales de las cuentas correspondientes de forma resiliente y precisa
        $acc1 = Account::where('id', 1)->first()
            ?: Account::where('code', 'CAJA_CHICA')->first()
            ?: Account::where('type', 'cash')->first()
            ?: Account::where('name', 'like', '%caja%')->first();

        $acc2 = Account::where('id', 2)->first()
            ?: Account::where('code', 'BPICH')->first()
            ?: Account::where('name', 'like', '%pichincha%')->first()
            ?: Account::where('bank_name', 'like', '%pichincha%')->first();

        $acc3 = Account::where('id', 3)->first()
            ?: Account::where('code', 'BGA')->first()
            ?: Account::where('name', 'like', '%guayaquil%')->first()
            ?: Account::where('bank_name', 'like', '%guayaquil%')->first();

        $saldoCajaChica = $acc1 ? (float)($acc1->fresh()->saldo_actual ?? $acc1->current_balance) : 0.00;
        $saldoPichincha = $acc2 ? (float)($acc2->fresh()->saldo_actual ?? $acc2->current_balance) : 0.00;
        $saldoGuayaquil = $acc3 ? (float)($acc3->fresh()->saldo_actual ?? $acc3->current_balance) : 0.00;

        $initCash = $previousCount ? (float)$previousCount->cash_total : 0.0;
        $initPich = $previousCount ? (float)$previousCount->pichincha_total : 0.0;
        $initGuay = $previousCount ? (float)$previousCount->guayaquil_total : 0.0;

        // Calcular flujos del día específico (Ingresos y Egresos) registrados en el sistema
        $acc1Id = $acc1 ? $acc1->id : 1;
        $acc2Id = $acc2 ? $acc2->id : 2;
        $acc3Id = $acc3 ? $acc3->id : 3;

        $cashIn = (float)DB::table('financial_movements')->where('account_id', $acc1Id)->where('type', 'income')->whereDate('entry_date', $date)->sum('amount');
        $cashOut = (float)DB::table('financial_movements')->where('account_id', $acc1Id)->where('type', 'expense')->whereDate('entry_date', $date)->sum('amount');

        $pichIn = (float)DB::table('financial_movements')->where('account_id', $acc2Id)->where('type', 'income')->whereDate('entry_date', $date)->sum('amount');
        $pichOut = (float)DB::table('financial_movements')->where('account_id', $acc2Id)->where('type', 'expense')->whereDate('entry_date', $date)->sum('amount');

        $guayIn = (float)DB::table('financial_movements')->where('account_id', $acc3Id)->where('type', 'income')->whereDate('entry_date', $date)->sum('amount');
        $guayOut = (float)DB::table('financial_movements')->where('account_id', $acc3Id)->where('type', 'expense')->whereDate('entry_date', $date)->sum('amount');

        // Función para calcular transferencias del día contemplando transfer_date y created_at
        $calcTrans = function($accountId, $isFrom) use ($date) {
            $col = $isFrom ? 'from_account_id' : 'to_account_id';
            return (float)DB::table('internal_transfers')
                ->where($col, $accountId)
                ->where(function($q) use ($date) {
                    $q->whereDate('transfer_date', $date)
                      ->orWhere(function($sub) use ($date) {
                          $sub->whereNull('transfer_date')->whereDate('created_at', $date);
                      });
                })->sum('amount');
        };

        $transOut1 = $calcTrans($acc1Id, true);
        $transIn1  = $calcTrans($acc1Id, false);

        $transOut2 = $calcTrans($acc2Id, true);
        $transIn2  = $calcTrans($acc2Id, false);

        $transOut3 = $calcTrans($acc3Id, true);
        $transIn3  = $calcTrans($acc3Id, false);

        $totCashIn = $cashIn + $transIn1;
        $totCashOut = $cashOut + $transOut1;

        $totPichIn = $pichIn + $transIn2;
        $totPichOut = $pichOut + $transOut2;

        $totGuayIn = $guayIn + $transIn3;
        $totGuayOut = $guayOut + $transOut3;

        // Saldos Teóricos esperados al cierre de esa fecha = Saldo Inicial + Ingresos - Egresos
        $expCash = round($initCash + $totCashIn - $totCashOut, 2);
        $expPich = round($initPich + $totPichIn - $totPichOut, 2);
        $expGuay = round($initGuay + $totGuayIn - $totGuayOut, 2);
        $expTotal = round($expCash + $expPich + $expGuay, 2);

        // Traer todas las cuentas activas para sincronización completa
        $allAccounts = Account::where('is_active', true)->get()->map(function($acc) {
            return [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'bank_name' => $acc->bank_name,
                'saldo_actual' => (float)($acc->fresh()->saldo_actual ?? $acc->current_balance ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'date_formatted' => ucfirst($carbonDate->locale('es')->isoFormat('dddd DD [de] MMMM YYYY')),
            'already_counted' => !is_null($currentCount),
            'is_sealed' => $currentCount ? (bool)$currentCount->is_sealed : false,
            'current_data' => $currentCount,
            'initial_balances' => $previousCount ? [
                'cash' => $previousCount->cash_total,
                'pichincha' => $previousCount->pichincha_total,
                'guayaquil' => $previousCount->guayaquil_total,
                'total' => $previousCount->grand_total,
                'origin_date' => $previousCount->count_date ? $previousCount->count_date->format('Y-m-d') : null,
                'cash_details' => $previousCount->cash_details
            ] : [
                'cash' => 0,
                'pichincha' => 0,
                'guayaquil' => 0,
                'total' => 0,
                'origin_date' => null,
                'cash_details' => null
            ],
            'daily_movements' => [
                'cash' => ['income' => $totCashIn, 'expense' => $totCashOut, 'net' => $totCashIn - $totCashOut],
                'pichincha' => ['income' => $totPichIn, 'expense' => $totPichOut, 'net' => $totPichIn - $totPichOut],
                'guayaquil' => ['income' => $totGuayIn, 'expense' => $totGuayOut, 'net' => $totGuayIn - $totGuayOut],
            ],
            'expected_balances' => [
                'cash' => $expCash,
                'pichincha' => $expPich,
                'guayaquil' => $expGuay,
                'total' => $expTotal,
            ],
            'latest_overall_count' => $latestOverallCount ? [
                'id' => $latestOverallCount->id,
                'count_date' => $latestOverallCount->count_date ? $latestOverallCount->count_date->format('Y-m-d') : null,
                'date_formatted' => $latestOverallCount->count_date ? ucfirst($latestOverallCount->count_date->locale('es')->isoFormat('dddd DD [de] MMMM YYYY')) : null,
                'cash_total' => (float)$latestOverallCount->cash_total,
                'pichincha_total' => (float)$latestOverallCount->pichincha_total,
                'guayaquil_total' => (float)$latestOverallCount->guayaquil_total,
                'grand_total' => (float)$latestOverallCount->grand_total,
                'is_sealed' => (bool)$latestOverallCount->is_sealed,
                'observations' => $latestOverallCount->observations,
                'user_name' => $latestOverallCount->user ? $latestOverallCount->user->name : 'N/A',
                'created_at' => $latestOverallCount->created_at ? $latestOverallCount->created_at->format('d/m/Y H:i') : null,
            ] : null,
            'system_balances' => [
                'cash' => (float)$saldoCajaChica,
                'pichincha' => (float)$saldoPichincha,
                'guayaquil' => (float)$saldoGuayaquil,
            ],
            'accounts' => $allAccounts,
        ])
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
    }

    /**
     * Listado histórico y búsqueda de arqueos/cuadres realizados
     */
    public function history(Request $request)
    {
        $query = DailyCashCount::with('user:id,name')->orderBy('count_date', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('count_date', 'like', "%{$search}%")
                  ->orWhere('observations', 'like', "%{$search}%")
                  ->orWhereHas('user', function($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date')) {
            $query->where('count_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('count_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $counts = $query->paginate($perPage);

        $counts->getCollection()->transform(function ($item) {
            $rawDate = $item->count_date ? (is_string($item->count_date) ? substr($item->count_date, 0, 10) : $item->count_date->format('Y-m-d')) : null;
            $item->raw_date = $rawDate;
            $item->date_formatted = $rawDate ? Carbon::parse($rawDate)->format('d/m/Y') : '';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $counts
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
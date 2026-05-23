<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialMovement;
use App\Models\Account;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class FinanzasController extends Controller
{
    //
    public function getDashboardData()
    {
        // 1. Obtener movimientos recientes con sus relaciones
        $movements = FinancialMovement::with(['movable'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // 2. Calcular resumen del mes actual
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $summary = [
            'monthlyIncome' => (float) FinancialMovement::where('type', 'income')
                ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
                ->sum('amount'),

            'monthlyExpense' => (float) FinancialMovement::where('type', 'expense')
                ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
                ->sum('amount'),

            // El balance actual se puede calcular restando totales o sumando todo
            'currentBalance' => (float) FinancialMovement::where('type', 'income')->sum('amount') -
                               FinancialMovement::where('type', 'expense')->sum('amount')
        ];

        return response()->json([
            'movements' => $movements,
            'summary' => $summary
        ]);
    }

    public function generatePDF(Request $request)
    {
        try {
            $movements = FinancialMovement::with(['movable', 'account'])
                ->orderBy('entry_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Cargar nombres de cuentas para transferencias
            $allAccounts = Account::all()->keyBy('id');
            Log::info('Available accounts: ' . json_encode($allAccounts->pluck('name', 'id')));

            foreach ($movements as $movement) {
                try {
                    if ($movement->type === 'transfer' && is_array($movement->metadata)) {
                        Log::info('Processing transfer movement ID: ' . $movement->id . ', metadata: ' . json_encode($movement->metadata));

                        $fromAccountId = $movement->metadata['from_account'] ?? null;
                        $toAccountId = $movement->metadata['to_account'] ?? null;

                        Log::info('Looking for from_account_id: ' . $fromAccountId . ', to_account_id: ' . $toAccountId);
                        Log::info('from_account_id exists in array: ' . (isset($allAccounts[$fromAccountId]) ? 'YES' : 'NO'));
                        Log::info('to_account_id exists in array: ' . (isset($allAccounts[$toAccountId]) ? 'YES' : 'NO'));

                        // Obtener metadata como array, modificarlo y asignarlo de vuelta
                        $metadata = $movement->metadata;

                        if ($fromAccountId && isset($allAccounts[$fromAccountId])) {
                            $metadata['from_account_name'] = $allAccounts[$fromAccountId]->name;
                            Log::info('Set from_account_name to: ' . $allAccounts[$fromAccountId]->name);
                        } else {
                            Log::warning('from_account_id ' . $fromAccountId . ' not found in accounts array');
                        }

                        if ($toAccountId && isset($allAccounts[$toAccountId])) {
                            $metadata['to_account_name'] = $allAccounts[$toAccountId]->name;
                            Log::info('Set to_account_name to: ' . $allAccounts[$toAccountId]->name);
                        } else {
                            Log::warning('to_account_id ' . $toAccountId . ' not found in accounts array');
                        }

                        $movement->metadata = $metadata;
                        Log::info('Updated metadata: ' . json_encode($movement->metadata));
                    }
                } catch (\Exception $e) {
                    // Continuar con el siguiente movimiento si hay error
                    Log::warning('Error loading account names for movement: ' . $e->getMessage());
                }
            }

            $summary = [
                'totalIncome' => (float) FinancialMovement::where('type', 'income')->sum('amount'),
                'totalExpense' => (float) FinancialMovement::where('type', 'expense')->sum('amount'),
                'balance' => (float) FinancialMovement::where('type', 'income')->sum('amount') -
                            FinancialMovement::where('type', 'expense')->sum('amount'),
                'totalCount' => $movements->count()
            ];

            $pdf = Pdf::loadView('movimientos.pdf', [
                'movements' => $movements,
                'summary' => $summary
            ]);

            return $pdf->download('reporte_financiero_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
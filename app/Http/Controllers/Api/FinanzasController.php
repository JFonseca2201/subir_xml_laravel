<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialMovement;
use Illuminate\Http\Request;

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
}
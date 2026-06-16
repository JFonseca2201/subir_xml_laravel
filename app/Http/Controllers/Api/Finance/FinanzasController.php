<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinancialMovement;
use App\Models\Finance\Account;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            // Obtener todos los movimientos sin límite
            $movements = FinancialMovement::with(['movable', 'account'])
                ->orderBy('entry_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Total movements for PDF: ' . $movements->count());

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

            // Calcular resumen general de todos los movimientos
            $summary = [
                'totalIncome' => (float) $movements->where('type', 'income')->sum('amount'),
                'totalExpense' => (float) $movements->where('type', 'expense')->sum('amount'),
                'balance' => (float) $movements->where('type', 'income')->sum('amount') -
                    $movements->where('type', 'expense')->sum('amount'),
                'totalCount' => $movements->count()
            ];

            // Generar un solo PDF con todos los movimientos
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

    public function generateSinglePDF(int $id)
    {
        try {
            $movement = \App\Models\Finance\FinanceRecord::with(['paymentDistributions.account'])->findOrFail($id);

            // Preparar data compatible con la vista, que usaba FinancialMovement
            // Los campos relevantes son: type (income/expense/transfer), entry_date, description, amount
            // metadata (para transferencias), account (o paymentDistributions)

            // Transformar el type (0=income, 1=expense) a string para la vista
            $movementType = $movement->type === 0 ? 'income' : 'expense';
            $movement->type_string = $movementType;

            // Obtener las cuentas afectadas y mapear nombres
            $accountName = 'N/A';
            if ($movement->paymentDistributions && $movement->paymentDistributions->count() > 0) {
                $accountName = $movement->paymentDistributions->map(function ($pd) {
                    if (!$pd->account) return 'N/A';
                    switch ($pd->account->id) {
                        case 1:
                            return 'EFECTIVO';
                        case 2:
                            return 'Banco Pichincha';
                        case 3:
                            return 'Banco Guayaquil';
                        default:
                            return 'EFECTIVO';
                    }
                })->implode(', ');
            }

            // Preparar el logo
            $sucursal = \App\Models\Config\Sucursale::first();
            $logoBase64 = '';
            $logoPath = null;
            if ($sucursal && $sucursal->logo) {
                $tempPath = public_path($sucursal->logo);
                if (file_exists($tempPath)) {
                    $logoPath = $tempPath;
                } else {
                    $cleanLogo = str_replace('storage/', '', $sucursal->logo);
                    $tempPath = storage_path('app/public/' . $cleanLogo);
                    if (file_exists($tempPath)) {
                        $logoPath = $tempPath;
                    }
                }
            }
            if (!$logoPath || !file_exists($logoPath)) {
                $logoPath = public_path('assets/img/brand/logo.jpeg');
            }
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoMime = 'image/jpeg';
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                if ($ext === 'png') $logoMime = 'image/png';
                elseif ($ext === 'gif') $logoMime = 'image/gif';
                elseif ($ext === 'svg') $logoMime = 'image/svg+xml';
                $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
            }

            $pdf = Pdf::loadView('movimientos.single_pdf', [
                'movement' => $movement,
                'type_string' => $movementType,
                'account_name' => $accountName,
                'logoBase64' => $logoBase64
            ]);

            return $pdf->download('comprobante_' . $movementType . '_' . $id . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating single movement PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

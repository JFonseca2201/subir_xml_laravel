<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\FinanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('financeRecords')->get();

        // Agregar balance actual a cada cuenta
        $accounts->each(function ($account) {
            $account->current_balance = $account->current_balance;
        });

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string',
            'type' => 'required|in:cash,bank',
            'bank_name' => 'nullable|string',
            'initial_balance' => 'nullable|numeric',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ]);

        $account = Account::create($validated);

        return response()->json($account, 201);
    }

    public function show(Account $account)
    {
        return response()->json([
            'account' => $account,
            'balance' => $account->current_balance,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:accounts,code,' . $account->id,
            'name' => 'required|string',
            'type' => 'required|in:cash,bank',
            'bank_name' => 'nullable|string',
            'initial_balance' => 'nullable|numeric',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    public function destroy(Account $account)
    {
        return DB::transaction(function () use ($account) {
            // Verificar si hay movimientos asociados
            $movimientosCount = FinanceRecord::where('account_id', $account->id)->count();

            if ($movimientosCount > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar la cuenta porque tiene movimientos asociados',
                    'movimientos_count' => $movimientosCount
                ], 422);
            }

            // Verificar si es una cuenta de sistema
            if ($account->is_system) {
                return response()->json([
                    'message' => 'No se puede eliminar una cuenta del sistema'
                ], 422);
            }

            // Verificar si tiene saldo diferente de cero
            if ($account->current_balance != 0) {
                return response()->json([
                    'message' => 'No se puede eliminar una cuenta con saldo diferente de cero',
                    'current_balance' => $account->current_balance
                ], 422);
            }

            // Eliminar la cuenta
            $account->delete();

            return response()->json(['message' => 'Cuenta eliminada exitosamente']);
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\MovimientoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index()
    {
        return response()->json(Account::with('transactions')->get());
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
            $movimientosCount = MovimientoCuenta::where('cuenta_id', $account->id)->count();

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
            if ($account->saldo_actual != 0) {
                return response()->json([
                    'message' => 'No se puede eliminar una cuenta con saldo diferente de cero',
                    'saldo_actual' => $account->saldo_actual
                ], 422);
            }

            // Eliminar la cuenta
            $account->delete();

            return response()->json(['message' => 'Cuenta eliminada exitosamente']);
        });
    }
}

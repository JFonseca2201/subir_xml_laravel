<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::where('is_active', true)
            ->get()
            ->map(function ($account) {
                // Forzar el cálculo del saldo actual
                $currentBalance = $account->current_balance;
                
                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'bank_name' => $account->bank_name,
                    'initial_balance' => $account->initial_balance,
                    'current_balance' => $currentBalance,
                    'is_active' => $account->is_active,
                    'is_system' => $account->is_system,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Cuentas obtenidas exitosamente',
            'data' => $accounts
        ]);
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
        $account->delete();

        return response()->json(['message' => 'Cuenta eliminada']);
    }
}

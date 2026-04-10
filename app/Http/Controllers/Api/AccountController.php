<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    public function index()
    {
        try {
            // Intentar cargar con relaciones, si falla cargar sin ellas
            $accounts = Account::with(['transactions'])->get();
            return response()->json($accounts);
        } catch (\Exception $e) {
            Log::error('Error loading accounts with transactions: ' . $e->getMessage());

            // Cargar cuentas sin relaciones si hay error
            $accounts = Account::all();
            return response()->json($accounts);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'bank_name' => 'nullable|string|max:255',
            'initial_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $account = Account::create($validated);
            return response()->json([
                'status' => 201,
                'message' => 'Cuenta creada exitosamente',
                'account' => $account
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating account: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear la cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Account $account)
    {
        try {
            return response()->json([
                'status' => 200,
                'account' => $account,
                'balance' => $account->current_balance ?? $account->initial_balance,
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing account: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Error al mostrar la cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:cash,bank',
            'bank_name' => 'nullable|string|max:255',
            'initial_balance' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $account->update($validated);

            return response()->json([
                'status' => 200,
                'message' => 'Cuenta actualizada exitosamente',
                'account' => $account
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating account: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Account $account)
    {
        try {
            $account->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Cuenta eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting account: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar la cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

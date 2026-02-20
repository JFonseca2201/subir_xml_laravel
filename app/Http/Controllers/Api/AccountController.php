<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        return response()->json(Account::with('transactions')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:cash,bank',
            'bank_name' => 'nullable|string',
            'initial_balance' => 'nullable|numeric',
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
        $account->update($request->all());

        return response()->json($account);
    }

    public function destroy(Account $account)
    {
        $account->delete();

        return response()->json(['message' => 'Cuenta eliminada']);
    }
}

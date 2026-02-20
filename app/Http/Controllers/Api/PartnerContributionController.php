<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PartnerContribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerContributionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        $contributions = PartnerContribution::with('partner')
            ->when($search, function ($query) use ($search) {
                $query->whereHas('partner', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('identification', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return response()->json($contributions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'amount' => 'required|numeric|min:0.01',
            'contribution_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 1️⃣ Buscar Banco Guayaquil
            // $account = Account::where('code', 'BGA')->lockForUpdate()->firstOrFail();
            $account = Account::where('code', 'BGA')->lockForUpdate()->firstOrFail();

            // 2️⃣ Crear aporte
            $contribution = PartnerContribution::create([
                'partner_id' => $validated['partner_id'],
                'account_id' => $account->id,
                'contribution_date' => $validated['contribution_date'],
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // 3️⃣ Registrar transacción usando relación morph
            $contribution->transaction()->create([
                'account_id' => $account->id,
                'type' => 'income',
                'amount' => $validated['amount'],
                'description' =>
                    'Aporte de socio ID: ' . $validated['partner_id'] . ' - ' . $validated['contribution_date'],
                'concept' => 'Aporte socio',
            ]);

            // 4️⃣ Actualizar saldo de la cuenta
            $account->increment('initial_balance', $validated['amount']);

            DB::commit();

            return response()->json(
                [
                    'message' => 'Aporte registrado correctamente',
                    'contribution' => $contribution,
                ],
                201,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                [
                    'message' => 'Error al registrar el aporte',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function show(int $id)
    {
        $contribution = PartnerContribution::with(['partner', 'transaction'])->findOrFail($id);

        return response()->json($contribution);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'contribution_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            // 1️⃣ Buscar aporte
            $contribution = PartnerContribution::findOrFail($id);

            // 2️⃣ Actualizar aporte
            $contribution->update([
                'amount' => $validated['amount'],
                'contribution_date' => $validated['contribution_date'],
                'notes' => $validated['notes'] ?? $contribution->notes,
            ]);

            // 3️⃣ Actualizar la transacción asociada
            if ($contribution->transaction) {
                $contribution->transaction->update([
                    'amount' => $validated['amount'],
                    'description' =>
                        'Aporte de socio ID: ' . $contribution->partner_id . ' - ' . $validated['contribution_date'],
                ]);
            }

            return response()->json([
                'message' => 'Aporte actualizado correctamente',
                'contribution' => $contribution,
            ]);
        });
    }

    public function destroy(int $id)
    {
        return DB::transaction(function () use ($id) {
            // 1️⃣ Buscar aporte por ID
            $contribution = PartnerContribution::findOrFail($id);

            // 2️⃣ Borrar transacción asociada si existe
            if ($contribution->transaction) {
                $contribution->transaction->delete();
            }

            // 3️⃣ Borrar el aporte
            $contribution->delete();

            // 4️⃣ Actualizar saldo real de la cuenta (solo si tiene account_id)
            $account_balance = null;
            if ($contribution->account_id) {
                $account = Account::lockForUpdate()->findOrFail($contribution->account_id);

                // recalcular saldo real de todos los aportes restantes
                $new_balance = PartnerContribution::where('account_id', $account->id)->sum('amount');

                $account->update(['initial_balance' => $new_balance]);
                $account_balance = $new_balance;
            }

            // 5️⃣ Respuesta exitosa
            return response()->json([
                'message' => 'Aporte y transacción asociada eliminados correctamente',
                'account_balance' => $account_balance,
            ]);
        });
    }
}

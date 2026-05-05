<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $transactions = Transaction::with(['account', 'user', 'partner', 'employee', 'workOrder'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'Transacciones obtenidas exitosamente',
            'data' => [
                'transactions' => $transactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ]
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $result = match($validated['type']) {
                1 => $this->handleIncome($validated),
                2 => $this->handleExpense($validated),
                3 => $this->handleTransfer($validated),
                default => throw new \Exception('Tipo de transacción no válido'),
            };

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Movimiento registrado correctamente en Luxury Evys Tecnicentro',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la transacción: ' . $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Handle income transactions (Type 1)
     */
    private function handleIncome(array $data)
    {
        $transaction = Transaction::create([
            'account_id' => $data['account_id'],
            'type' => Transaction::TYPE_INCOME,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'partner_id' => $data['partner_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $account = Account::find($data['account_id']);

        return [
            'transaction' => $transaction->load(['account', 'partner', 'workOrder']),
            'new_balance' => $account->current_balance,
            'account_id' => $account->id
        ];
    }

    /**
     * Handle expense transactions (Type 2)
     */
    private function handleExpense(array $data)
    {
        $account = Account::find($data['account_id']);
        
        if ($account->current_balance < $data['amount']) {
            throw new \Exception('Saldo insuficiente en la cuenta');
        }

        $transaction = Transaction::create([
            'account_id' => $data['account_id'],
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'partner_id' => $data['partner_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'user_id' => auth()->id(),
        ]);

        return [
            'transaction' => $transaction->load(['account', 'employee']),
            'new_balance' => $account->current_balance,
            'account_id' => $account->id
        ];
    }

    /**
     * Handle transfer transactions (Type 3)
     */
    private function handleTransfer(array $data)
    {
        if (empty($data['account_id']) || empty($data['account_destination_id'])) {
            throw new \Exception('Se requieren cuenta origen y destino para transferencias');
        }

        if ($data['account_id'] == $data['account_destination_id']) {
            throw new \Exception('Las cuentas origen y destino no pueden ser iguales');
        }

        $sourceAccount = Account::find($data['account_id']);
        
        if ($sourceAccount->current_balance < $data['amount']) {
            throw new \Exception('Saldo insuficiente en la cuenta origen');
        }

        $transferGroupId = (string) \Illuminate\Support\Str::uuid();

        // Create expense transaction (debit from source account)
        $debitTransaction = Transaction::create([
            'account_id' => $data['account_id'],
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => $data['amount'],
            'description' => 'Transferencia salida: ' . $data['description'],
            'user_id' => auth()->id(),
        ]);

        // Create income transaction (credit to destination account)
        $creditTransaction = Transaction::create([
            'account_id' => $data['account_destination_id'],
            'type' => Transaction::TYPE_INCOME,
            'amount' => $data['amount'],
            'description' => 'Transferencia entrada: ' . $data['description'],
            'user_id' => auth()->id(),
        ]);

        $destinationAccount = Account::find($data['account_destination_id']);

        return [
            'transactions' => [
                'debit' => $debitTransaction->load('account'),
                'credit' => $creditTransaction->load('account')
            ],
            'transfer_group_id' => $transferGroupId,
            'balances' => [
                'source' => [
                    'account_id' => $sourceAccount->id,
                    'new_balance' => $sourceAccount->current_balance
                ],
                'destination' => [
                    'account_id' => $destinationAccount->id,
                    'new_balance' => $destinationAccount->current_balance
                ]
            ]
        ];
    }
}

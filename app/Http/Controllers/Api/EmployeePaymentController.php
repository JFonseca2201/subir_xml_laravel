<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\EmployeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeePaymentController extends Controller
{
    public function index()
    {
        return response()->json(EmployeePayment::latest()->paginate(10));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_name' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'concept' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $account = Account::where('name', 'Banco Guayaquil')->firstOrFail();

            if ($account->current_balance < $validated['amount']) {
                return response()->json(
                    [
                        'message' => 'Saldo insuficiente',
                    ],
                    422,
                );
            }

            $payment = EmployeePayment::create($validated);

            $payment->transaction()->create([
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => $validated['amount'],
                'concept' => 'Pago trabajador',
                'description' => $validated['concept'],
                'transaction_date' => $validated['payment_date'],
            ]);

            return response()->json($payment->load('transaction'), 201);
        });
    }

    public function destroy(EmployeePayment $employeePayment)
    {
        return DB::transaction(function () use ($employeePayment) {
            $employeePayment->transaction()->delete();
            $employeePayment->delete();

            return response()->json(['message' => 'Pago eliminado']);
        });
    }
}

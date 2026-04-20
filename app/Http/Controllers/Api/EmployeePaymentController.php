<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\EmployeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeePaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeePayment::query();

        // Búsqueda global
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('concept', 'like', "%$search%")
                    ->orWhereHas('employee', function ($empQuery) use ($search) {
                        $empQuery->where('name', 'like', "%$search%")
                            ->orWhere('surname', 'like', "%$search%")
                            ->orWhere('full_name', 'like', "%$search%");
                    });
            });
        }

        // Filtros exactos
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->get('employee_id'));
        }

        if ($request->filled('state')) {
            $query->where('state', $request->get('state'));
        }

        if ($request->filled('sucursale_id')) {
            $query->where('sucursale_id', $request->get('sucursale_id'));
        }

        // Búsqueda por rango de fechas
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Validar formato de fecha
            if (strtotime($dateFrom) && strtotime($dateTo)) {
                $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
            }
        } elseif ($request->filled('date_from')) {
            $dateFrom = $request->get('date_from');
            if (strtotime($dateFrom)) {
                $query->where('payment_date', '>=', $dateFrom);
            }
        } elseif ($request->filled('date_to')) {
            $dateTo = $request->get('date_to');
            if (strtotime($dateTo)) {
                $query->where('payment_date', '<=', $dateTo);
            }
        }

        $per_page = $request->get('per_page', 10);
        $payments = $query->with(['employee', 'user', 'sucursal', 'advances'])
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'total' => $payments->total(),
            'count' => $payments->count(),
            'per_page' => $payments->perPage(),
            'current_page' => $payments->currentPage(),
            'total_pages' => $payments->lastPage(),
            'from' => $payments->firstItem(),
            'to' => $payments->lastItem(),
            'has_more_pages' => $payments->hasMorePages(),
            'next_page_url' => $payments->nextPageUrl(),
            'prev_page_url' => $payments->previousPageUrl(),
            'first_page_url' => $payments->url(1),
            'last_page_url' => $payments->url($payments->lastPage()),
            'payments' => $payments->items(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'concept' => 'nullable|string',
            'payment_type' => 'required|string|in:cash,transfer,check',
            'account_id' => 'required|integer|exists:accounts,id',
            'state' => 'nullable|integer|in:1,2',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
            'advance_ids' => 'nullable|array',
            'advance_ids.*' => 'integer|exists:employee_advances,id',
        ], [
            'employee_id.required' => 'El ID del empleado es obligatorio.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'payment_type.required' => 'El tipo de pago es obligatorio.',
            'payment_type.in' => 'El tipo de pago debe ser cash (Efectivo), transfer (Transferencia) o check (Cheque).',
            'account_id.required' => 'La cuenta de pago es obligatoria.',
            'account_id.exists' => 'La cuenta seleccionada no existe.',
            'state.in' => 'El estado debe ser 1 (Activo) o 2 (Inactivo).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $employeeId = $request->get('employee_id');
            $paymentAmount = $request->get('amount');

            // Obtener información del empleado incluyendo sueldo
            $employee = \App\Models\Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Empleado no encontrado',
                ], 404);
            }

            // Validar que el monto del pago no exceda el sueldo del empleado
            if ($paymentAmount > $employee->salary) {
                return response()->json([
                    'status' => 422,
                    'message' => 'El monto del pago no puede exceder el sueldo del empleado',
                    'employee_salary' => $employee->salary,
                    'payment_amount' => $paymentAmount,
                    'difference' => $paymentAmount - $employee->salary,
                ], 422);
            }

            // Verificar adelantos pendientes del empleado
            $pendingAdvances = \App\Models\EmployeeAdvance::where('employee_id', $employeeId)
                ->where('state', 1) // Pendientes
                ->get();

            $totalPendingAdvances = $pendingAdvances->sum('amount');
            $selectedAdvanceIds = $request->get('advance_ids', []);

            // Validar que los adelantos seleccionados pertenezcan al empleado y estén pendientes
            if (!empty($selectedAdvanceIds)) {
                $invalidAdvances = \App\Models\EmployeeAdvance::whereIn('id', $selectedAdvanceIds)
                    ->where(function ($query) use ($employeeId) {
                        $query->where('employee_id', '!=', $employeeId)
                            ->orWhere('state', '!=', 1);
                    })
                    ->count();

                if ($invalidAdvances > 0) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Algunos adelantos seleccionados no son válidos o no están pendientes',
                    ], 422);
                }
            }

            $account = Account::find($request->get('account_id'));
            if (!$account) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Cuenta no encontrada',
                ], 404);
            }

            if ($account->current_balance < $request->get('amount')) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Saldo insuficiente en la cuenta seleccionada',
                    'account_name' => $account->name,
                    'account_balance' => $account->current_balance,
                    'payment_amount' => $request->get('amount'),
                    'shortfall' => $request->get('amount') - $account->current_balance,
                ], 422);
            }

            $payment = EmployeePayment::create($request->only([
                'employee_id',
                'user_id',
                'amount',
                'payment_date',
                'concept',
                'payment_type',
                'state',
                'sucursale_id',
            ]));

            // Procesar adelantos seleccionados
            if (!empty($selectedAdvanceIds)) {
                $advancesToDiscount = \App\Models\EmployeeAdvance::whereIn('id', $selectedAdvanceIds)->get();

                foreach ($advancesToDiscount as $advance) {
                    // Actualizar estado del adelanto
                    $advance->update([
                        'state' => 2, // Descontado
                        'employee_payment_id' => $payment->id
                    ]);

                    // Crear AccountTransaction para el adelanto descontado
                    \App\Models\AccountTransaction::create([
                        'account_id' => $account->id,
                        'type' => 'expense',
                        'category' => 'salary_advance',
                        'amount' => $advance->amount,
                        'description' => 'Adelanto descontado - ' . ($advance->concept ?? 'Sin concepto'),
                        'reference_id' => $advance->id,
                        'reference_type' => 'employee_advance',
                        'transaction_date' => $request->get('payment_date'),
                    ]);
                }
            }

            // Debitar el monto de la cuenta seleccionada
            $account->current_balance = $account->current_balance - $request->get('amount');
            $account->save();

            \App\Models\AccountTransaction::create([
                'account_id' => $account->id,
                'type' => 'expense',
                'category' => 'salary_payment',
                'amount' => $request->get('amount'),
                'description' => $request->get('concept'),
                'reference_id' => $payment->id,
                'reference_type' => 'employee_payment',
                'transaction_date' => $request->get('payment_date'),
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Pago registrado exitosamente',
                'payment' => $payment->load(['employee', 'user', 'sucursal', 'advances']),
                'pending_advances' => $pendingAdvances,
                'total_pending_advances' => $totalPendingAdvances,
                'discounted_advances_count' => count($selectedAdvanceIds),
                'net_amount' => $payment->net_amount,
                'employee_salary' => $employee->salary,
                'salary_remaining' => $employee->salary - $paymentAmount,
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'previous_balance' => $account->current_balance + $request->get('amount'),
                    'new_balance' => $account->current_balance,
                ],
            ], 201);
        });
    }

    /**
     * Check pending advances for an employee before creating payment
     */
    public function checkPendingAdvances($employeeId)
    {
        // Obtener información del empleado
        $employee = \App\Models\Employee::find($employeeId);
        if (!$employee) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
            ], 404);
        }

        $pendingAdvances = \App\Models\EmployeeAdvance::where('employee_id', $employeeId)
            ->where('state', 1) // Pendientes
            ->with(['employee', 'user', 'sucursal'])
            ->orderBy('advance_date', 'asc')
            ->get();

        $totalPending = $pendingAdvances->sum('amount');

        return response()->json([
            'status' => 200,
            'employee' => $employee,
            'employee_salary' => $employee->salary,
            'pending_advances' => $pendingAdvances,
            'total_pending_advances' => $totalPending,
            'advances_count' => $pendingAdvances->count(),
            'available_for_payment' => $employee->salary - $totalPending,
        ]);
    }

    /**
     * Get available accounts for payments
     */
    public function getAvailableAccounts()
    {
        $accounts = \App\Models\Account::where('state', 1) // Activas
            ->select('id', 'name', 'current_balance', 'type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 200,
            'accounts' => $accounts,
            'total_accounts' => $accounts->count(),
        ]);
    }

    public function destroy(EmployeePayment $employeePayment)
    {
        return DB::transaction(function () use ($employeePayment) {
            // Eliminar AccountTransaction asociada
            $existingTransaction = \App\Models\AccountTransaction::where('reference_type', 'employee_payment')
                ->where('reference_id', $employeePayment->id)
                ->first();

            if ($existingTransaction) {
                $existingTransaction->delete();
            }

            // Devolver adelantos a estado pendiente
            $employeePayment->advances()->update([
                'state' => 1, // Pendiente
                'employee_payment_id' => null
            ]);

            $employeePayment->delete();

            return response()->json(['message' => 'Pago eliminado']);
        });
    }
}

<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeePayment;
use App\Models\Employee\EmployeeAdvance;
use App\Models\Employee\Employee;
use App\Models\Finance\Account;
use App\Models\Finance\MovimientoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeePayment::with(['employee', 'account', 'creator'])
            ->whereNull('deleted_at')
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filtrar por tipo si se especifica (solo para pagos)
        if ($request->has('type') && $request->type === 'payment') {
            $query->where('type', 'payment');
        }

        // Filtrar por rango de fechas
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }

        $payments = $query->get();

        // Agrupar por fecha
        $agrupados = $payments->groupBy(function ($payment) {
            return Carbon::parse($payment->payment_date)->format('Y-m-d');
        });

        // Formatear datos
        $data = [];
        $totalPagos = 0;
        $totalAdelantos = 0;

        foreach ($agrupados as $fecha => $pagosDia) {
            $totalDia = $pagosDia->sum('amount');

            $pagosFormateados = $pagosDia->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'employee_name' => $payment->employee ? $payment->employee->first_name . ' ' . $payment->employee->last_name : 'N/A',
                    'amount' => (float) $payment->amount,
                    'description' => $payment->description,
                    'account_name' => $payment->account ? $payment->account->name : 'N/A',
                    'payment_method' => $payment->payment_method,
                    'date' => Carbon::parse($payment->payment_date)->format('d/m/Y'),
                    'created_by' => $payment->creator ? $payment->creator->name : 'N/A',
                    'type' => $payment->type,
                    'reference' => $payment->reference
                ];
            });

            $data[] = [
                'date' => $pagosDia->first()->payment_date,
                'label' => $this->formatDateLabel($fecha),
                'total_dia' => (float) $totalDia,
                'payments' => $pagosFormateados,
            ];

            $totalPagos += $totalDia;
        }

        // Obtener adelantos por separado
        $queryAdvances = EmployeeAdvance::with(['employee', 'account', 'creator'])
            ->whereNull('deleted_at')
            ->orderBy('advance_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filtrar por tipo si se especifica (solo para adelantos)
        if ($request->has('type') && $request->type === 'advance') {
            $queryAdvances->where('type', 'advance');
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $queryAdvances->whereBetween('advance_date', [$request->start_date, $request->end_date]);
        }

        $advances = $queryAdvances->get();

        $agrupadosAdvances = $advances->groupBy(function ($advance) {
            return Carbon::parse($advance->advance_date)->format('Y-m-d');
        });

        foreach ($agrupadosAdvances as $fecha => $adelantosDia) {
            $totalDia = $adelantosDia->sum('amount');

            $adelantosFormateados = $adelantosDia->map(function ($advance) {
                return [
                    'id' => $advance->id,
                    'employee_id' => $advance->employee_id,
                    'employee_name' => $advance->employee ? $advance->employee->first_name . ' ' . $advance->employee->last_name : 'N/A',
                    'amount' => (float) $advance->amount,
                    'description' => $advance->description,
                    'account_id' => $advance->account_id,
                    'account_name' => $advance->account->name ?? 'N/A',
                    'payment_method' => $advance->payment_method,
                    'date' => Carbon::parse($advance->advance_date)->format('d/m/Y'),
                    'reason' => $advance->reason,
                    'created_by' => $advance->creator->name ?? 'N/A',
                    'type' => $advance->type,
                    'is_deducted' => (bool) $advance->is_deducted,
                ];
            });

            $data[] = [
                'date' => $adelantosDia->first()->advance_date,
                'label' => $this->formatDateLabel($fecha),
                'total_dia' => (float) $totalDia,
                'advances' => $adelantosFormateados,
            ];

            $totalAdelantos += $totalDia;
        }

        // Flatten the data for frontend
        $allExpenses = [];
        $allAdvances = [];

        foreach ($data as $dayData) {
            if (isset($dayData['payments'])) {
                foreach ($dayData['payments'] as $payment) {
                    $allExpenses[] = $payment;
                }
            }
            if (isset($dayData['advances'])) {
                foreach ($dayData['advances'] as $advance) {
                    $allAdvances[] = $advance;
                }
            }
        }

        return response()->json([
            'payments' => $allExpenses,
            'advances' => $allAdvances,
            'summary' => [
                'total_payments' => (float) $totalPagos,
                'total_advances' => (float) $totalAdelantos,
                'total_general' => (float) ($totalPagos + $totalAdelantos)
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'reference' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            // Obtener adelantos pendientes del empleado
            $pendingAdvances = EmployeeAdvance::where('employee_id', $request->employee_id)
                ->whereNull('deleted_at')
                ->where('is_deducted', false)
                ->get();

            $totalPendingAdvances = $pendingAdvances->sum('amount');
            $finalPaymentAmount = $request->amount;
            $deductedAdvances = [];

            // Si hay adelantos pendientes, deducirlos del pago
            if ($totalPendingAdvances > 0) {
                $finalPaymentAmount = max(0, $request->amount - $totalPendingAdvances);

                // Marcar adelantos como deducidos
                foreach ($pendingAdvances as $advance) {
                    $advance->update(['is_deducted' => true]);
                    $deductedAdvances[] = [
                        'id' => $advance->id,
                        'amount' => (float) $advance->amount,
                        'description' => $advance->description,
                    ];
                }
            }

            // Validar saldo disponible en la cuenta
            $account = Account::findOrFail($request->account_id);
            if ($account->saldo_actual < $finalPaymentAmount) {
                return response()->json([
                    'message' => 'Saldo insuficiente en la cuenta',
                    'saldo_disponible' => $account->saldo_actual,
                    'monto_solicitado' => $finalPaymentAmount
                ], 422);
            }

            $payment = EmployeePayment::create([
                'employee_id' => $request->employee_id,
                'account_id' => $request->account_id,
                'amount' => $finalPaymentAmount,
                'description' => $request->description,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'type' => 'payment',
                'created_by' => auth()->id(),
            ]);

            $payment->registerMovement(
                $request->account_id,
                'expense', // Tipo: Egreso
                $finalPaymentAmount,
                "Pago de nómina a: " . ($payment->employee->full_name ?? 'Empleado'),
                $request->payment_date,
                [
                    'payment_method' => $request->payment_method,
                    'reference' => $request->reference,
                    'original_amount' => $request->amount,
                    'deductions' => $totalPendingAdvances
                ]
            );

            // Crear movimiento contable
            MovimientoCuenta::create([
                'cuenta_id' => $request->account_id,
                'tipo' => 'INGRESO',
                'monto' => $finalPaymentAmount,
                'descripcion' => "Pago a empleado: {$request->description}",
                'referencia' => 'employee_payment',
                'referencia_id' => $payment->id,
                'fecha' => $request->payment_date,
            ]);

            // Actualizar saldo de la cuenta
            $account = Account::findOrFail($request->account_id);
            $account->decrement('saldo_actual', $finalPaymentAmount);

            return response()->json([
                'payment' => $payment,
                'original_amount' => (float) $request->amount,
                'final_amount' => (float) $finalPaymentAmount,
                'total_advances_deducted' => (float) $totalPendingAdvances,
                'deducted_advances' => $deductedAdvances,
                'message' => $totalPendingAdvances > 0
                    ? "Pago procesado con descuento de $" . number_format($totalPendingAdvances, 2) . " en adelantos"
                    : "Pago procesado correctamente"
            ], 201);
        });
    }

    public function storeAdvance(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'advance_date' => 'required|date',
            'payment_method' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'reason' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            // Validar saldo disponible en la cuenta
            $account = Account::findOrFail($request->account_id);
            if ($account->saldo_actual < $request->amount) {
                return response()->json([
                    'message' => 'Saldo insuficiente en la cuenta',
                    'saldo_disponible' => $account->saldo_actual,
                    'monto_solicitado' => $request->amount
                ], 422);
            }

            $advance = EmployeeAdvance::create([
                'employee_id' => $request->employee_id,
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'description' => $request->description,
                'advance_date' => $request->advance_date,
                'payment_method' => $request->payment_method,
                'reason' => $request->reason,
                'type' => 'advance',
                'created_by' => auth()->id(),
            ]);

            $advance->registerMovement(
                $request->account_id,
                'expense', // Tipo: Egreso
                $request->amount,
                "Adelanto de sueldo: " . ($advance->employee->full_name ?? 'Empleado'),
                $request->advance_date,
                [
                    'reason' => $request->reason,
                    'payment_method' => $request->payment_method
                ]
            );

            // Crear movimiento contable (EGRESO porque es un adelanto)
            MovimientoCuenta::create([
                'cuenta_id' => $request->account_id,
                'tipo' => 'EGRESO',
                'monto' => $request->amount,
                'descripcion' => "Adelanto a empleado: {$request->description}",
                'referencia' => 'employee_advance',
                'referencia_id' => $advance->id,
                'fecha' => $request->advance_date,
            ]);

            // Actualizar saldo de la cuenta (RESTAR porque es un adelanto)
            $account = Account::findOrFail($request->account_id);
            $account->decrement('saldo_actual', $request->amount);

            return response()->json($advance, 201);
        });
    }

    public function update(Request $request, $id)
    {
        $expense = EmployeePayment::findOrFail($id);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'reference' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $expense) {
            // Obtener valores anteriores
            $cuentaAnterior = $expense->account_id;
            $montoAnterior = $expense->amount;

            // Si cambió la cuenta o el monto, ajustar movimientos
            if ($cuentaAnterior != $request->account_id || $montoAnterior != $request->amount) {
                // Devolver saldo anterior a la cuenta original (porque el pago original ya lo había restado)
                $cuentaAnt = Account::findOrFail($cuentaAnterior);
                $cuentaAnt->increment('saldo_actual', $montoAnterior);

                // Crear movimiento de reversión
                MovimientoCuenta::create([
                    'cuenta_id' => $cuentaAnterior,
                    'tipo' => 'INGRESO', // Es un ingreso porque estamos devolviendo el dinero
                    'monto' => $montoAnterior,
                    'descripcion' => "Reverso por edición de pago: {$expense->description}",
                    'referencia' => 'reverso_edicion_pago',
                    'referencia_id' => $expense->id,
                    'fecha' => now()->toDateString(),
                ]);

                // Validar saldo disponible en la nueva cuenta
                $cuentaNueva = Account::findOrFail($request->account_id);
                if ($cuentaAnterior != $request->account_id && $cuentaNueva->saldo_actual < $request->amount) {
                    return response()->json([
                        'message' => 'Saldo insuficiente en la nueva cuenta',
                        'saldo_disponible' => $cuentaNueva->saldo_actual,
                        'monto_solicitado' => $request->amount
                    ], 422);
                }

                // Aplicar nuevo movimiento (EGRESO porque es un pago)
                MovimientoCuenta::create([
                    'cuenta_id' => $request->account_id,
                    'tipo' => 'EGRESO',
                    'monto' => $request->amount,
                    'descripcion' => "Pago a empleado (editado): {$request->description}",
                    'referencia' => 'employee_payment',
                    'referencia_id' => $expense->id,
                    'fecha' => $request->payment_date,
                ]);

                // Restar nuevo saldo de la cuenta nueva
                $cuentaNueva->decrement('saldo_actual', $request->amount);
            }

            // Actualizar pago
            $expense->update([
                'employee_id' => $request->employee_id,
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'description' => $request->description,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
            ]);
            $expense->registerMovement(
                $request->account_id,
                'expense',
                $request->amount,
                "Pago editado: " . $request->description,
                $request->payment_date
            );

            return response()->json($expense);
        });
    }

    public function updateAdvance(Request $request, $id)
    {
        $advance = EmployeeAdvance::findOrFail($id);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'advance_date' => 'required|date',
            'payment_method' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'reason' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $advance) {
            // Obtener valores anteriores
            $cuentaAnterior = $advance->account_id;
            $montoAnterior = $advance->amount;

            // Si cambió la cuenta o el monto, ajustar movimientos
            if ($cuentaAnterior != $request->account_id || $montoAnterior != $request->amount) {
                // Revertir movimiento anterior (INGRESO porque era un adelanto)
                MovimientoCuenta::create([
                    'cuenta_id' => $cuentaAnterior,
                    'tipo' => 'INGRESO',
                    'monto' => $montoAnterior,
                    'descripcion' => "Reverso por edición de adelanto: {$advance->description}",
                    'referencia' => 'reverso_edicion_adelanto',
                    'referencia_id' => $advance->id,
                    'fecha' => now()->toDateString(),
                ]);

                // Sumar saldo anterior (devolver el adelanto)
                $cuentaAnt = Account::findOrFail($cuentaAnterior);
                $cuentaAnt->increment('saldo_actual', $montoAnterior);

                // Validar saldo disponible en la nueva cuenta
                $cuentaNueva = Account::findOrFail($request->account_id);
                if ($cuentaAnterior != $request->account_id && $cuentaNueva->saldo_actual < $request->amount) {
                    return response()->json([
                        'message' => 'Saldo insuficiente en la nueva cuenta',
                        'saldo_disponible' => $cuentaNueva->saldo_actual,
                        'monto_solicitado' => $request->amount
                    ], 422);
                }

                // Aplicar nuevo movimiento (EGRESO porque es un adelanto)
                MovimientoCuenta::create([
                    'cuenta_id' => $request->account_id,
                    'tipo' => 'EGRESO',
                    'monto' => $request->amount,
                    'descripcion' => "Adelanto a empleado (editado): {$request->description}",
                    'referencia' => 'employee_advance',
                    'referencia_id' => $advance->id,
                    'fecha' => $request->advance_date,
                ]);

                // Restar nuevo saldo
                $cuentaNueva->decrement('saldo_actual', $request->amount);
            }

            // Actualizar adelanto
            $advance->update([
                'employee_id' => $request->employee_id,
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'description' => $request->description,
                'advance_date' => $request->advance_date,
                'payment_method' => $request->payment_method,
                'reason' => $request->reason,
            ]);

            $advance->registerMovement(
                $request->account_id,
                'expense',
                $request->amount,
                "Adelanto editado: " . $request->description,
                $request->advance_date
            );

            return response()->json($advance);
        });
    }

    public function destroy($id)
    {
        $expense = EmployeePayment::findOrFail($id);

        return DB::transaction(function () use ($expense) {
            // Eliminar pago
            $expense->delete();
            $expense->financialMovement()->delete();

            // Crear movimiento reverso
            MovimientoCuenta::create([
                'cuenta_id' => $expense->account_id,
                'tipo' => 'INGRESO', // Es un ingreso porque estamos devolviendo el dinero del pago
                'monto' => $expense->amount,
                'descripcion' => "Reverso por eliminación de pago: {$expense->description}",
                'referencia' => 'reverso_eliminacion_pago',
                'referencia_id' => $expense->id,
                'fecha' => now()->toDateString(),
            ]);

            // Devolver saldo a la cuenta (restituir el pago eliminado)
            $account = Account::findOrFail($expense->account_id);
            $account->increment('saldo_actual', $expense->amount);

            return response()->json(['message' => 'Pago eliminado exitosamente']);
        });
    }

    public function destroyAdvance($id)
    {
        $advance = EmployeeAdvance::findOrFail($id);

        return DB::transaction(function () use ($advance) {
            // Eliminar adelanto
            $advance->delete();
            $advance->financialMovement()->delete();


            // Crear movimiento reverso (INGRESO porque se devuelve el adelanto)
            MovimientoCuenta::create([
                'cuenta_id' => $advance->account_id,
                'tipo' => 'INGRESO',
                'monto' => $advance->amount,
                'descripcion' => "Reverso por eliminación de adelanto: {$advance->description}",
                'referencia' => 'reverso_eliminacion_adelanto',
                'referencia_id' => $advance->id,
                'fecha' => now()->toDateString(),
            ]);

            // Sumar saldo de la cuenta (devolver el adelanto)
            $account = Account::findOrFail($advance->account_id);
            $account->increment('saldo_actual', $advance->amount);

            return response()->json(['message' => 'Adelanto eliminado exitosamente']);
        });
    }

    public function getEmployeePendingAdvances($employeeId)
    {
        try {
            $employee = Employee::findOrFail($employeeId);

            // Obtener adelantos pendientes del empleado
            $pendingAdvances = EmployeeAdvance::with(['account', 'creator'])
                ->where('employee_id', $employeeId)
                ->whereNull('deleted_at')
                ->where('is_deducted', false)
                ->orderBy('advance_date', 'asc')
                ->get();

            $totalPendingAdvances = $pendingAdvances->sum('amount');

            return response()->json([
                'employee_id' => $employeeId,
                'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                'pending_advances' => $pendingAdvances->map(function ($advance) {
                    return [
                        'id' => $advance->id,
                        'amount' => (float) $advance->amount,
                        'description' => $advance->description,
                        'advance_date' => Carbon::parse($advance->advance_date)->format('d/m/Y'),
                        'account_name' => $advance->account ? $advance->account->name : 'N/A',
                        'reason' => $advance->reason,
                        'created_at' => Carbon::parse($advance->created_at)->format('d/m/Y H:i'),
                    ];
                }),
                'total_pending_amount' => (float) $totalPendingAdvances,
                'advances_count' => $pendingAdvances->count(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Error al obtener adelantos pendientes: ' . $th->getMessage()
            ], 500);
        }
    }

    public function getEmployeeEarnings($employeeId)
    {
        try {
            $employee = Employee::findOrFail($employeeId);

            // Obtener adelantos del empleado
            $totalAdvances = EmployeeAdvance::where('employee_id', $employeeId)
                ->whereNull('deleted_at')
                ->sum('amount');

            // Calcular ganancias (puedes ajustar esta fórmula según tus necesidades)
            $baseSalary = $employee->salary;
            $currentDate = Carbon::now();
            $hiredDate = Carbon::parse($employee->hired_at);

            // Calcular días trabajados en el mes actual
            $daysWorkedThisMonth = $currentDate->day;
            $dailyRate = $baseSalary / 30; // Asumiendo 30 días por mes

            // Ganancias del mes actual (proporcional)
            $monthlyEarnings = $dailyRate * $daysWorkedThisMonth;

            // Ganancias disponibles para pago (restando adelantos)
            $availableForPayment = $monthlyEarnings /* - $totalAdvances */;

            return response()->json([
                'employee_id' => $employeeId,
                'base_salary' => $baseSalary,
                'monthly_earnings' => $monthlyEarnings,
                'total_advances' => $totalAdvances,
                'available_for_payment' => $availableForPayment,
                'days_worked_this_month' => $daysWorkedThisMonth,
                'daily_rate' => $dailyRate,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Error al calcular ganancias del empleado: ' . $th->getMessage()
            ], 500);
        }
    }

    private function formatDateLabel($date)
    {
        $timezone = 'America/Guayaquil';
        $carbonDate = Carbon::parse($date, $timezone);
        
        $hoy = Carbon::now($timezone)->format('Y-m-d');
        $ayer = Carbon::now($timezone)->subDay()->format('Y-m-d');
        $fechaFormat = $carbonDate->format('Y-m-d');

        if ($fechaFormat === $hoy) {
            return 'Hoy';
        } elseif ($fechaFormat === $ayer) {
            return 'Ayer';
        } else {
            return $carbonDate->locale('es')->translatedFormat('l d F');
        }
    }
}
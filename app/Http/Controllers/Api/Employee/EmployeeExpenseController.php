<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeePayment;
use App\Models\Employee\EmployeeAdvance;
use App\Models\Employee\Employee;
use App\Models\Finance\Account;
use App\Models\Finance\MovimientoCuenta;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeePayment::with(['employee', 'account', 'creator', 'attachments'])
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
                    'employee_id' => $payment->employee_id,
                    'employee_name' => $payment->employee ? $payment->employee->first_name . ' ' . $payment->employee->last_name : 'N/A',
                    'amount' => (float) $payment->amount,
                    'base_salary' => (float) ($payment->base_salary ?: $payment->amount),
                    'advances_amount' => (float) ($payment->advances_amount ?: 0),
                    'net_amount' => (float) ($payment->net_amount ?: $payment->amount),
                    'payment_month' => $payment->payment_month,
                    'description' => $payment->description,
                    'account_id' => $payment->account_id,
                    'account_name' => $payment->account ? ($payment->account->bank_name ?: $payment->account->name) : 'N/A',
                    'payment_method' => $payment->payment_method,
                    'date' => Carbon::parse($payment->payment_date)->format('d/m/Y'),
                    'payment_date' => $payment->payment_date,
                    'created_by' => $payment->creator ? $payment->creator->name : 'N/A',
                    'type' => $payment->type,
                    'reference' => $payment->reference,
                    'attachments' => $payment->attachments ? $payment->attachments->map(function ($att) {
                        return [
                            'id' => $att->id,
                            'file_name' => $att->file_name,
                            'file_path' => $att->file_path,
                            'url' => $att->url,
                            'download_url' => url("api/attachments/{$att->id}/download"),
                            'is_image' => str_starts_with($att->mime_type ?? '', 'image/'),
                            'is_pdf' => ($att->mime_type === 'application/pdf'),
                            'mime_type' => $att->mime_type,
                            'file_size' => $att->file_size,
                        ];
                    })->values() : [],
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
        $queryAdvances = EmployeeAdvance::with(['employee', 'account', 'creator', 'attachments'])
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
                    'account_name' => $advance->account ? ($advance->account->bank_name ?: $advance->account->name) : 'N/A',
                    'payment_method' => $advance->payment_method,
                    'date' => Carbon::parse($advance->advance_date)->format('d/m/Y'),
                    'reason' => $advance->reason,
                    'created_by' => $advance->creator->name ?? 'N/A',
                    'type' => $advance->type,
                    'is_deducted' => (bool) $advance->is_deducted,
                    'attachments' => $advance->attachments ? $advance->attachments->map(function ($att) {
                        return [
                            'id' => $att->id,
                            'file_name' => $att->file_name,
                            'file_path' => $att->file_path,
                            'url' => $att->url,
                            'download_url' => url("api/attachments/{$att->id}/download"),
                            'is_image' => str_starts_with($att->mime_type ?? '', 'image/'),
                            'is_pdf' => ($att->mime_type === 'application/pdf'),
                            'mime_type' => $att->mime_type,
                            'file_size' => $att->file_size,
                        ];
                    })->values() : [],
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

    public function checkMonthPayment(Request $request)
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $month = $request->month;

        $existing = EmployeePayment::where('employee_id', $request->employee_id)
            ->where('payment_month', $month)
            ->whereNull('deleted_at')
            ->first();

        $monthNames = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        $parts = explode('-', $month);
        $monthLabel = ($monthNames[$parts[1] ?? ''] ?? $parts[1]) . ' ' . ($parts[0] ?? '');

        if ($existing) {
            return response()->json([
                'is_paid' => true,
                'message' => "El sueldo correspondiente al mes de {$monthLabel} ya fue pagado a este empleado el día " . Carbon::parse($existing->payment_date)->format('d/m/Y') . " por un valor neto de $" . number_format($existing->amount, 2) . ".",
                'existing_payment' => [
                    'id' => $existing->id,
                    'amount' => (float) $existing->amount,
                    'base_salary' => (float) ($existing->base_salary ?: $existing->amount),
                    'advances_amount' => (float) ($existing->advances_amount ?: 0),
                    'payment_date' => Carbon::parse($existing->payment_date)->format('d/m/Y'),
                    'payment_method' => $existing->payment_method,
                ],
                'month_label' => $monthLabel
            ]);
        }

        // Pendientes por pagar
        $baseSalary = (float) ($employee->salary ?: 0);
        $pendingAdvances = EmployeeAdvance::where('employee_id', $request->employee_id)
            ->whereNull('deleted_at')
            ->where('is_deducted', false)
            ->orderBy('advance_date', 'asc')
            ->get();

        $totalAdvances = (float) $pendingAdvances->sum('amount');
        $netAmount = max(0, $baseSalary - $totalAdvances);

        return response()->json([
            'is_paid' => false,
            'base_salary' => $baseSalary,
            'total_advances' => $totalAdvances,
            'net_amount' => $netAmount,
            'pending_advances' => $pendingAdvances->map(function ($adv) {
                return [
                    'id' => $adv->id,
                    'amount' => (float) $adv->amount,
                    'description' => $adv->description,
                    'reason' => $adv->reason,
                    'advance_date' => Carbon::parse($adv->advance_date)->format('d/m/Y'),
                ];
            }),
            'month_label' => $monthLabel
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'payment_date' => 'required|date',
            'payment_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_method' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'reference' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $employee = Employee::findOrFail($request->employee_id);
            $month = $request->payment_month;

            // 1. Validar si ya existe un pago para este empleado en este mes
            $existingPayment = EmployeePayment::where('employee_id', $request->employee_id)
                ->where('payment_month', $month)
                ->whereNull('deleted_at')
                ->first();

            if ($existingPayment) {
                $monthNames = [
                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                ];
                $parts = explode('-', $month);
                $monthLabel = ($monthNames[$parts[1] ?? ''] ?? $parts[1]) . ' ' . ($parts[0] ?? '');

                return response()->json([
                    'message' => "El sueldo correspondiente a {$monthLabel} ya ha sido pagado a este empleado (Pago #{$existingPayment->id} por $" . number_format($existingPayment->amount, 2) . ").",
                    'error' => 'month_already_paid',
                    'existing_payment' => $existingPayment
                ], 422);
            }

            // 2. Obtener salario base
            $baseSalary = (float) ($request->base_salary ?: ($employee->salary ?: ($request->amount ?: 0)));

            // 3. Obtener adelantos pendientes del empleado
            $pendingAdvances = EmployeeAdvance::where('employee_id', $request->employee_id)
                ->whereNull('deleted_at')
                ->where('is_deducted', false)
                ->get();

            $totalPendingAdvances = (float) $pendingAdvances->sum('amount');
            $finalPaymentAmount = max(0, $baseSalary - $totalPendingAdvances);
            $deductedAdvances = [];

            // 4. Validar saldo disponible en la cuenta
            $account = Account::findOrFail($request->account_id);
            if ($account->saldo_actual < $finalPaymentAmount) {
                return response()->json([
                    'message' => 'Saldo insuficiente en la cuenta',
                    'saldo_disponible' => $account->saldo_actual,
                    'monto_solicitado' => $finalPaymentAmount
                ], 422);
            }

            // 5. Crear el registro de pago con mes y desglose
            $payment = EmployeePayment::create([
                'employee_id' => $request->employee_id,
                'account_id' => $request->account_id,
                'amount' => $finalPaymentAmount,
                'base_salary' => $baseSalary,
                'advances_amount' => $totalPendingAdvances,
                'net_amount' => $finalPaymentAmount,
                'description' => $request->description ?: "Pago de nómina {$month} - {$employee->first_name} {$employee->last_name}",
                'payment_date' => $request->payment_date,
                'payment_month' => $month,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'type' => 'payment',
                'created_by' => auth()->id(),
            ]);

            // 6. Marcar adelantos como deducidos y vincularlos a este pago
            foreach ($pendingAdvances as $advance) {
                $advance->update([
                    'is_deducted' => true,
                    'employee_payment_id' => $payment->id,
                ]);
                $deductedAdvances[] = [
                    'id' => $advance->id,
                    'amount' => (float) $advance->amount,
                    'description' => $advance->description,
                    'reason' => $advance->reason,
                ];
            }

            $payment->registerMovement(
                $request->account_id,
                'expense', // Tipo: Egreso
                $finalPaymentAmount,
                "Pago de nómina {$month} a: " . ($employee->first_name . ' ' . $employee->last_name),
                $request->payment_date,
                [
                    'payment_method' => $request->payment_method,
                    'reference' => $request->reference,
                    'base_salary' => $baseSalary,
                    'deductions' => $totalPendingAdvances,
                    'payment_month' => $month,
                ]
            );

            // Crear movimiento contable
            MovimientoCuenta::create([
                'cuenta_id' => $request->account_id,
                'tipo' => 'EGRESO',
                'monto' => $finalPaymentAmount,
                'descripcion' => "Pago de nómina {$month} a empleado: {$employee->first_name} {$employee->last_name}",
                'referencia' => 'employee_payment',
                'referencia_id' => $payment->id,
                'fecha' => $request->payment_date,
            ]);

            // Actualizar saldo de la cuenta
            $account = Account::findOrFail($request->account_id);
            $account->decrement('saldo_actual', $finalPaymentAmount);

            // Crear FinanceRecord para que afecte el current_balance del frontend
            $financeRecord = FinanceRecord::create([
                'type' => FinanceRecord::TYPE_EXPENSE,
                'account_id' => $request->account_id,
                'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                'amount' => $finalPaymentAmount,
                'description' => "Pago de nómina {$month}: {$employee->first_name} {$employee->last_name}",
                'entry_date' => $request->payment_date,
                'user_id' => auth()->id() ?? 1,
                'invoice_number' => 'PAGO-EMP-' . $payment->id,
            ]);

            PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $request->account_id,
                'amount' => $finalPaymentAmount,
                'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
            ]);

            // Adjuntar comprobantes si fueron enviados en el request
            if ($request->hasFile('receipts')) {
                try {
                    $storageService = app(\App\Services\InvoiceStorageService::class);
                    $empName = $employee->first_name . ' ' . $employee->last_name;
                    $identifier = "PAGO-EMP-" . str_pad($payment->id, 5, '0', STR_PAD_LEFT);
                    $storageService->attachReceiptsToModel(
                        $payment,
                        $identifier,
                        $empName,
                        $request->file('receipts'),
                        Carbon::parse($request->payment_date),
                        'receipt',
                        'egresos'
                    );
                    if (isset($financeRecord)) {
                        $storageService->attachReceiptsToModel(
                            $financeRecord,
                            $identifier,
                            $empName,
                            $request->file('receipts'),
                            Carbon::parse($request->payment_date),
                            'receipt',
                            'egresos'
                        );
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error al adjuntar comprobantes a pago de empleado:', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'payment' => $payment->load(['employee', 'account', 'creator', 'attachments', 'advances']),
                'base_salary' => (float) $baseSalary,
                'final_amount' => (float) $finalPaymentAmount,
                'total_advances_deducted' => (float) $totalPendingAdvances,
                'deducted_advances' => $deductedAdvances,
                'message' => $totalPendingAdvances > 0
                    ? "Pago procesado: Sueldo Base $" . number_format($baseSalary, 2) . " - Adelantos $" . number_format($totalPendingAdvances, 2) . " = Neto $" . number_format($finalPaymentAmount, 2)
                    : "Pago de nómina procesado correctamente por $" . number_format($finalPaymentAmount, 2)
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

            // Adjuntar comprobantes si fueron enviados en el request
            if ($request->hasFile('receipts')) {
                try {
                    $storageService = app(\App\Services\InvoiceStorageService::class);
                    $empName = $advance->employee ? ($advance->employee->first_name . ' ' . $advance->employee->last_name) : 'EMPLEADO';
                    $identifier = "ADEL-EMP-" . str_pad($advance->id, 5, '0', STR_PAD_LEFT);
                    $storageService->attachReceiptsToModel(
                        $advance,
                        $identifier,
                        $empName,
                        $request->file('receipts'),
                        Carbon::parse($request->advance_date),
                        'receipt',
                        'egresos'
                    );
                } catch (\Exception $e) {
                    \Log::warning('Error al adjuntar comprobantes a adelanto de empleado:', ['error' => $e->getMessage()]);
                }
            }

            // Crear FinanceRecord para que afecte el current_balance del frontend
            $financeRecord = FinanceRecord::create([
                'type' => FinanceRecord::TYPE_EXPENSE,
                'account_id' => $request->account_id,
                'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                'amount' => $request->amount,
                'description' => "Adelanto a empleado: {$request->description}",
                'entry_date' => $request->advance_date,
                'user_id' => auth()->id() ?? 1,
                'invoice_number' => 'ADELANTO-EMP-' . $advance->id,
            ]);

            PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
            ]);

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

            // Actualizar FinanceRecord
            $financeRecord = FinanceRecord::where('invoice_number', 'PAGO-EMP-' . $expense->id)->first();
            if ($financeRecord) {
                $financeRecord->update([
                    'account_id' => $request->account_id,
                    'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                    'amount' => $request->amount,
                    'description' => "Pago a empleado (editado): {$request->description}",
                    'entry_date' => $request->payment_date,
                ]);

                // Actualizar o recrear PaymentDistribution
                $financeRecord->paymentDistributions()->delete();
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $request->account_id,
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                ]);
            }

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

            // Actualizar FinanceRecord
            $financeRecord = FinanceRecord::where('invoice_number', 'ADELANTO-EMP-' . $advance->id)->first();
            if ($financeRecord) {
                $financeRecord->update([
                    'account_id' => $request->account_id,
                    'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                    'amount' => $request->amount,
                    'description' => "Adelanto a empleado (editado): {$request->description}",
                    'entry_date' => $request->advance_date,
                ]);

                // Actualizar o recrear PaymentDistribution
                $financeRecord->paymentDistributions()->delete();
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $request->account_id,
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method === 'EFECTIVO' ? 'cash' : 'transfer',
                ]);
            }

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

            // Eliminar FinanceRecord
            $financeRecord = FinanceRecord::where('invoice_number', 'PAGO-EMP-' . $expense->id)->first();
            if ($financeRecord) {
                $financeRecord->paymentDistributions()->delete();
                $financeRecord->delete();
            }

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

            // Eliminar FinanceRecord
            $financeRecord = FinanceRecord::where('invoice_number', 'ADELANTO-EMP-' . $advance->id)->first();
            if ($financeRecord) {
                $financeRecord->paymentDistributions()->delete();
                $financeRecord->delete();
            }

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

    public function generateSinglePDF($type, $id)
    {
        try {
            $record = null;
            $fecha = null;

            if ($type === 'payment') {
                $record = EmployeePayment::with(['employee', 'account'])->findOrFail($id);
                $fecha = $record->payment_date;
            } else if ($type === 'advance') {
                $record = EmployeeAdvance::with(['employee', 'account'])->findOrFail($id);
                $fecha = $record->advance_date;
            } else {
                return response()->json(['error' => 'Tipo inválido'], 400);
            }

            // Preparar el logo
            $sucursal = \App\Models\Config\Sucursale::query()->first();
            $logoBase64 = '';
            $logoPath = null;
            if ($sucursal && $sucursal->logo) {
                $tempPath = public_path($sucursal->logo);
                if (file_exists($tempPath)) {
                    $logoPath = $tempPath;
                } else {
                    $cleanLogo = str_replace('storage/', '', $sucursal->logo);
                    $tempPath = storage_path('app/public/' . $cleanLogo);
                    if (file_exists($tempPath)) {
                        $logoPath = $tempPath;
                    }
                }
            }
            if (!$logoPath || !file_exists($logoPath)) {
                $logoPath = public_path('assets/img/brand/logo.jpeg');
            }
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoMime = 'image/jpeg';
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                if ($ext === 'png') $logoMime = 'image/png';
                elseif ($ext === 'gif') $logoMime = 'image/gif';
                elseif ($ext === 'svg') $logoMime = 'image/svg+xml';
                $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
            }

            // Mapear nombre de la cuenta
            $accountName = $record->account ? ($record->account->bank_name ?: $record->account->name) : 'EFECTIVO';

            $employeeName = $record->employee ? $record->employee->first_name . ' ' . $record->employee->last_name : 'N/A';

            // Si es PAGO DE NÓMINA -> Generar Rol de Pagos oficial
            if ($type === 'payment') {
                $employee = $record->employee;
                $advances = $record->advances;

                // Si no tiene adelantos vinculados directos en la relación
                if (!$advances || $advances->isEmpty()) {
                    $advances = EmployeeAdvance::where('employee_id', $record->employee_id)
                        ->where(function ($q) use ($record) {
                            $q->where('employee_payment_id', $record->id)
                                ->orWhere(function ($sub) use ($record) {
                                    $sub->where('is_deducted', true)
                                        ->whereBetween('updated_at', [
                                            Carbon::parse($record->created_at)->subMinutes(10),
                                            Carbon::parse($record->created_at)->addMinutes(10)
                                        ]);
                                });
                        })
                        ->get();
                }

                // Si aún está vacío pero tiene mes definido, buscar los de ese mes
                $monthStr = $record->payment_month;
                if ((!$advances || $advances->isEmpty())) {
                    $desc = strtoupper($record->description ?? '');
                    if (!$monthStr) {
                        if (strpos($desc, 'AGOSTO') !== false) $monthStr = '2026-08';
                        elseif (strpos($desc, 'JULIO') !== false) $monthStr = '2026-07';
                        elseif (strpos($desc, 'JUNIO') !== false) $monthStr = '2026-06';
                        elseif (strpos($desc, 'MAYO') !== false) $monthStr = '2026-05';
                    }

                    if ($monthStr) {
                        $mStart = Carbon::parse($monthStr . '-01')->startOfMonth();
                        $mEnd = Carbon::parse($monthStr . '-01')->endOfMonth();
                        $advances = EmployeeAdvance::where('employee_id', $record->employee_id)
                            ->where('is_deducted', true)
                            ->whereBetween('advance_date', [$mStart, $mEnd])
                            ->get();
                    }
                }

                $monthNames = [
                    '01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO', '04' => 'ABRIL',
                    '05' => 'MAYO', '06' => 'JUNIO', '07' => 'JULIO', '08' => 'AGOSTO',
                    '09' => 'SEPTIEMBRE', '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE'
                ];

                if (!$monthStr) {
                    $dt = Carbon::parse($record->payment_date);
                    $monthStr = $dt->day <= 10 ? $dt->copy()->subMonth()->format('Y-m') : $dt->format('Y-m');
                }

                if ($monthStr && strpos($monthStr, '-') !== false) {
                    $parts = explode('-', $monthStr);
                    $monthLabel = ($monthNames[$parts[1] ?? ''] ?? $parts[1]) . ' ' . ($parts[0] ?? '');
                } else {
                    $dt = Carbon::parse($record->payment_date);
                    $mKey = str_pad($dt->month, 2, '0', STR_PAD_LEFT);
                    $monthLabel = ($monthNames[$mKey] ?? '') . ' ' . $dt->year;
                }

                $baseSalary = (float) ($record->base_salary > 0 ? $record->base_salary : ($employee ? $employee->salary : $record->amount));
                $advancesAmount = (float) ($record->advances_amount > 0 ? $record->advances_amount : ($advances ? $advances->sum('amount') : 0));
                $netAmount = (float) ($record->net_amount > 0 ? $record->net_amount : ($record->amount > 0 ? $record->amount : ($baseSalary - $advancesAmount)));

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.employee_payroll_role', [
                    'payment' => $record,
                    'employee' => $employee,
                    'advances' => $advances ?: [],
                    'base_salary' => $baseSalary,
                    'advances_amount' => $advancesAmount,
                    'net_amount' => $netAmount,
                    'month_label' => $monthLabel,
                    'payment_date' => Carbon::parse($record->payment_date)->format('d/m/Y'),
                    'doc_number' => 'ROL-EMP-' . str_pad($record->id, 5, '0', STR_PAD_LEFT),
                    'account_name' => $accountName,
                    'company_name' => $sucursal ? ($sucursal->trade_name ?: ($sucursal->name ?: 'EMPRESA')) : 'LAVADORA Y LUBRICADORA EXPRESS',
                    'company_ruc' => $sucursal ? ($sucursal->ruc ?: '1790012345001') : '1790012345001',
                    'company_address' => $sucursal ? ($sucursal->address ?: 'Av. Principal') : 'Av. Principal',
                    'company_phone' => $sucursal ? ($sucursal->phone ?: '') : '',
                    'company_email' => $sucursal ? ($sucursal->email ?: '') : '',
                    'logoBase64' => $logoBase64,
                    'amount_in_words' => $this->convertNumberToSpanishWords($netAmount)
                ])->setPaper('a4', 'portrait');

                $cleanEmpName = str_replace(' ', '_', $employeeName);
                return $pdf->download('ROL_PAGOS_' . $cleanEmpName . '_' . ($monthStr ?: date('Y-m')) . '.pdf');
            }

            // Si es ADELANTO -> Generar Comprobante de Adelanto
            $movement = new \stdClass();
            $movement->id = $record->id;
            $movement->entry_date = $fecha;
            $movement->created_at = $record->created_at;
            $movement->description = 'Adelanto de Sueldo: ' . ($record->employee ? ($record->employee->first_name . ' ' . $record->employee->last_name) : '') .
                ($record->description ? ' - ' . $record->description : '') .
                ($record->reason ? ' (Motivo: ' . $record->reason . ')' : '');
            $movement->amount = $record->amount;
            $movement->work_order_number = null;
            $movement->invoice_number = $record->reference ?? ('ADEL-EMP-' . str_pad($record->id, 5, '0', STR_PAD_LEFT));

            $customTitle = 'Comprobante de Adelanto de Sueldo - ' . $employeeName;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('movimientos.single_pdf', [
                'movement' => $movement,
                'type_string' => 'expense',
                'account_name' => $accountName,
                'logoBase64' => $logoBase64,
                'custom_title' => $customTitle
            ]);

            return $pdf->download($type . '_' . $id . '_' . $employeeName . '_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generating employee single PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function convertNumberToSpanishWords($number)
    {
        $number = round((float) $number, 2);
        $intPart = (int) floor($number);
        $cents = (int) round(($number - $intPart) * 100);

        $words = '';
        if ($intPart === 0) {
            $words = 'CERO';
        } elseif ($intPart === 100) {
            $words = 'CIEN';
        } else {
            $thousands = (int) floor($intPart / 1000);
            $remainder = $intPart % 1000;

            if ($thousands > 0) {
                if ($thousands === 1) {
                    $words .= 'MIL ';
                } else {
                    $words .= $this->threeDigitsToWords($thousands) . ' MIL ';
                }
            }

            if ($remainder > 0) {
                $words .= $this->threeDigitsToWords($remainder);
            }
        }

        $centsFormatted = str_pad($cents, 2, '0', STR_PAD_LEFT);
        return "SON: " . trim($words) . " CON {$centsFormatted}/100 DÓLARES AMERICANOS";
    }

    private function threeDigitsToWords($num)
    {
        if ($num === 100) return 'CIEN';
        $units = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $tens = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $specials = [
            11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            21 => 'VEINTIÚN', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE'
        ];
        $hundreds = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $h = (int) floor($num / 100);
        $rem = $num % 100;
        $t = (int) floor($rem / 10);
        $u = $rem % 10;

        $res = '';
        if ($h > 0) $res .= $hundreds[$h] . ' ';

        if (isset($specials[$rem])) {
            $res .= $specials[$rem];
        } else {
            if ($t > 0) {
                $res .= $tens[$t];
                if ($u > 0) $res .= ' Y ' . $units[$u];
            } elseif ($u > 0) {
                $res .= $units[$u];
            }
        }

        return trim($res);
    }
}

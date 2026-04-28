<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyCashFlow;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DailyCashFlowController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DailyCashFlow::with(['account', 'user'])
            ->orderBy('flow_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filtrar por tipo si se especifica
        if ($request->has('flow_type')) {
            $query->where('flow_type', $request->get('flow_type'));
        }

        // Filtrar por rango de fechas si se especifica
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('flow_date', [
                $request->get('start_date'),
                $request->get('end_date')
            ]);
        }

        // Filtrar por tipo de cuenta si se especifica
        if ($request->has('account_type')) {
            $query->where('account_type', $request->get('account_type'));
        }

        // Filtrar por account_id si se especifica
        if ($request->has('account_id')) {
            $query->where('account_id', $request->get('account_id'));
        }

        $flows = $query->paginate(20);

        // Formatear respuesta incluyendo account_id
        $formattedFlows = collect($flows->items())->map(function ($flow) {
            return [
                'id' => $flow->id,
                'flow_type' => $flow->flow_type,
                'flow_date' => $flow->flow_date,
                'flow_date_formatted' => $flow->flow_date_formatted,
                'order_number' => $flow->order_number,
                'order_id' => $flow->order_id,
                'total_amount' => $flow->total_amount,
                'formatted_amount' => $flow->formatted_amount,
                'payment_status' => $flow->payment_status,
                'payment_status_description' => $flow->payment_status_description,
                'payment_method' => $flow->payment_method,
                'payment_method_description' => $flow->payment_method_description,
                'description' => $flow->description,
                'account_type' => $flow->account_type,
                'account_type_description' => $flow->account_type_description,
                'account_id' => $flow->account_id,
                'account' => $flow->account ? [
                    'id' => $flow->account->id,
                    'name' => $flow->account->name,
                    'type' => $flow->account->type,
                ] : null,
                'source_type' => $flow->source_type,
                'source_type_description' => $flow->source_type_description,
                'source_id' => $flow->source_id,
                'user' => [
                    'id' => $flow->user->id,
                    'name' => $flow->user->name,
                ],
                'created_at' => $flow->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $flow->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'flows' => $formattedFlows,
            'pagination' => [
                'total' => $flows->total(),
                'count' => $flows->count(),
                'per_page' => $flows->perPage(),
                'current_page' => $flows->currentPage(),
                'total_pages' => $flows->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'flow_type' => 'required|in:income,expense',
            'flow_date' => 'nullable|date',
            'order_number' => 'nullable|string|max:50',
            'order_id' => 'nullable|integer',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_status' => 'required|in:complete,partial,pending',
            'description' => 'nullable|string|max:500',
            'account_type' => 'required|integer|in:1,2,3', // 1=caja chica, 2=caja, 3=bancos
            'account_id' => 'nullable|exists:accounts,id',
            'payment_method' => 'nullable|in:cash,transfer',
            'user_id' => 'required|exists:users,id',
            'source_type' => 'required|in:sale,purchase,other',
            'source_id' => 'nullable|integer',
        ]);

        // Si no se proporciona flow_date, no asignar fecha actual
        if (!isset($validated['flow_date']) || empty($validated['flow_date'])) {
            return response()->json([
                'status' => 422,
                'message' => 'El campo flow_date es requerido',
                'field' => 'flow_date',
            ], 422);
        }

        // Validar y obtener cuenta específica según tipo de pago
        if (isset($validated['account_id'])) {
            $account = Account::find($validated['account_id']);
            if (!$account) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Cuenta no encontrada',
                    'account_id' => $validated['account_id'],
                ], 422);
            }

            // Validar que el tipo de pago coincida con el tipo de cuenta
            $expectedPaymentMethod = $account->type === 'cash' ? 'cash' : 'transfer';

            if (isset($validated['payment_method']) && $validated['payment_method'] !== $expectedPaymentMethod) {
                return response()->json([
                    'status' => 422,
                    'message' => 'El método de pago no corresponde al tipo de cuenta',
                    'account_type' => $account->type,
                    'expected_payment_method' => $expectedPaymentMethod,
                    'provided_payment_method' => $validated['payment_method'],
                ], 422);
            }

            // Asignar método de pago automáticamente si no se proporciona
            if (!isset($validated['payment_method'])) {
                $validated['payment_method'] = $expectedPaymentMethod;
            }
        }

        return DB::transaction(function () use ($validated) {
            // Si es ingreso, convertir descripción a mayúsculas y generar número consecutivo
            if ($validated['flow_type'] === 'income') {
                // Convertir descripción a mayúsculas
                if (isset($validated['description'])) {
                    $validated['description'] = strtoupper($validated['description']);
                }

                // Generar número consecutivo automático si no se proporciona
                if (!isset($validated['order_number']) || empty($validated['order_number'])) {
                    $lastOrder = DailyCashFlow::where('flow_type', 'income')
                        ->whereNotNull('order_number')
                        ->orderBy('order_number', 'desc')
                        ->first();

                    if ($lastOrder) {
                        // Extraer número del último registro y sumar 1
                        $lastNumber = preg_replace('/[^0-9]/', '', $lastOrder->order_number);
                        $nextNumber = (int)$lastNumber + 1;
                        $validated['order_number'] = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                    } else {
                        $validated['order_number'] = '0001';
                    }
                }
            }

            $flow = DailyCashFlow::create($validated);

            // Si es ingreso, actualizar saldo de la cuenta específica
            if ($validated['flow_type'] === 'income' && isset($validated['account_id'])) {
                $account = Account::find($validated['account_id']);
                if ($account) {
                    $account->update([
                        'current_balance' => $account->current_balance + $validated['total_amount']
                    ]);
                }
            }

            // Si es egreso, actualizar saldo de la cuenta específica
            if ($validated['flow_type'] === 'expense' && isset($validated['account_id'])) {
                $account = Account::find($validated['account_id']);
                if ($account) {
                    $account->update([
                        'current_balance' => $account->current_balance - $validated['total_amount']
                    ]);
                }
            }

            return response()->json([
                'status' => 201,
                'message' => 'Flujo de caja registrado correctamente',
                'flow' => $flow->load(['account', 'user']),
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(DailyCashFlow $dailyCashFlow)
    {
        $flow = $dailyCashFlow->load(['account', 'user']);

        return response()->json([
            'status' => 200,
            'flow' => [
                'id' => $flow->id,
                'flow_type' => $flow->flow_type,
                'flow_date' => $flow->flow_date,
                'flow_date_formatted' => $flow->flow_date_formatted,
                'order_number' => $flow->order_number,
                'order_id' => $flow->order_id,
                'total_amount' => $flow->total_amount,
                'formatted_amount' => $flow->formatted_amount,
                'payment_status' => $flow->payment_status,
                'payment_status_description' => $flow->payment_status_description,
                'payment_method' => $flow->payment_method,
                'payment_method_description' => $flow->payment_method_description,
                'description' => $flow->description,
                'account_type' => $flow->account_type,
                'account_type_description' => $flow->account_type_description,
                'account_id' => (int) $flow->account_id, // Asegurar que sea entero
                'account' => $flow->account ? [
                    'id' => $flow->account->id,
                    'name' => $flow->account->name,
                    'type' => $flow->account->type,
                ] : null,
                'source_type' => $flow->source_type,
                'source_type_description' => $flow->source_type_description,
                'source_id' => $flow->source_id,
                'user' => $flow->user ? [
                    'id' => $flow->user->id,
                    'name' => $flow->user->name,
                ] : null,
                'created_at' => $flow->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $flow->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DailyCashFlow $dailyCashFlow)
    {
        $validated = $request->validate([
            'flow_type' => 'required|in:income,expense',
            'flow_date' => 'required|date',
            'order_number' => 'nullable|string|max:50',
            'order_id' => 'nullable|integer',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_status' => 'required|in:complete,partial,pending',
            'description' => 'nullable|string|max:500',
            'account_type' => 'required|integer|in:1,2,3',
            'account_id' => 'nullable|exists:accounts,id',
            'source_type' => 'required|in:sale,purchase,other',
            'source_id' => 'nullable|integer',
        ]);

        // Validar que flow_date sea proporcionado explícitamente
        if (!isset($validated['flow_date']) || empty($validated['flow_date'])) {
            return response()->json([
                'status' => 422,
                'message' => 'El campo flow_date es requerido para actualizar',
                'field' => 'flow_date',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $dailyCashFlow) {
            // Obtener valores anteriores
            $oldAmount = $dailyCashFlow->total_amount;
            $oldFlowType = $dailyCashFlow->flow_type;
            $oldAccountId = $dailyCashFlow->account_id;
            $oldOrderNumber = $dailyCashFlow->order_number;

            // Si es ingreso, convertir descripción a mayúsculas y mantener número de orden
            $dailyCashFlow->update($validated);

            return response()->json([
                'flow' => $dailyCashFlow->fresh(['account', 'user']),
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.  
     */
    public function destroy(DailyCashFlow $dailyCashFlow)
    {
        return DB::transaction(function () use ($dailyCashFlow) {
            // Revertir saldo si hay cuenta asociada
            if ($dailyCashFlow->account_id) {
                $account = \App\Models\Account::find($dailyCashFlow->account_id);
                if ($account) {
                    if ($dailyCashFlow->flow_type === 'income') {
                        $account->update([
                            'current_balance' => $account->current_balance - $dailyCashFlow->total_amount
                        ]);
                    } else {
                        $account->update([
                            'current_balance' => $account->current_balance + $dailyCashFlow->total_amount
                        ]);
                    }
                }
            }

            // Eliminar el flujo
            $dailyCashFlow->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Flujo de caja eliminado correctamente',
                'deleted_flow_id' => $dailyCashFlow->id,
                'amount_reverted' => $dailyCashFlow->total_amount,
            ]);
        });
    }

    /**
     * Get daily summary
     */
    public function dailySummary(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $flows = DailyCashFlow::whereDate('flow_date', $date)
            ->with(['account', 'user'])
            ->get();

        $income = $flows->where('flow_type', 'income')->sum('total_amount');
        $expense = $flows->where('flow_type', 'expense')->sum('total_amount');
        $balance = $income - $expense;

        return response()->json([
            'date' => $date,
            'total_income' => $income,
            'total_expense' => $expense,
            'daily_balance' => $balance,
            'flows' => $flows,
            'flow_count' => $flows->count(),
        ]);
    }

    /**
     * Get options for daily cash flow form
     */
    public function getOptions()
    {
        return response()->json([
            'flow_types' => [
                ['label' => 'Ingreso', 'value' => 'income'],
                ['label' => 'Egreso', 'value' => 'expense']
            ],
            'payment_methods' => [
                ['label' => 'Efectivo', 'value' => 'cash'],
                ['label' => 'Transferencia', 'value' => 'transfer']
            ],
            'payment_statuses' => [
                ['label' => 'Completo', 'value' => 'complete'],
                ['label' => 'Parcial', 'value' => 'partial'],
                ['label' => 'Pendiente', 'value' => 'pending']
            ],
            'source_types' => [
                ['label' => 'Venta', 'value' => 'sale'],
                ['label' => 'Compra', 'value' => 'purchase'],
                ['label' => 'Otro', 'value' => 'other']
            ]
        ]);
    }

    /**
     * Get monthly transactions grouped by day
     */
    public function monthlyTransactions(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
            'flow_type' => 'nullable|in:income,expense',
            'account_type' => 'nullable|integer|in:1,2,3',
        ]);

        $year = $validated['year'];
        $month = str_pad($validated['month'], 2, '0', STR_PAD_LEFT);

        // Construir consulta base
        $query = DailyCashFlow::with(['account', 'user'])
            ->whereYear('flow_date', $year)
            ->whereMonth('flow_date', $month);

        // Aplicar filtros opcionales
        if (isset($validated['flow_type'])) {
            $query->where('flow_type', $validated['flow_type']);
        }

        if (isset($validated['account_type'])) {
            $query->where('account_type', $validated['account_type']);
        }

        // Obtener todas las transacciones del mes
        $flows = $query->orderBy('flow_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por día
        $groupedByDay = $flows->groupBy(function ($flow) {
            return $flow->flow_date->format('Y-m-d');
        });

        // Formatear respuesta con estadísticas por día
        $dailyStats = [];
        $monthlyIncome = 0;
        $monthlyExpense = 0;
        $totalTransactions = 0;

        foreach ($groupedByDay as $date => $dayFlows) {
            $dayIncome = $dayFlows->where('flow_type', 'income')->sum('total_amount');
            $dayExpense = $dayFlows->where('flow_type', 'expense')->sum('total_amount');
            $dayBalance = $dayIncome - $dayExpense;

            $dailyStats[] = [
                'date' => $date,
                'formatted_date' => $dayFlows->first()->flow_date->format('d/m/Y'),
                'day_name' => $dayFlows->first()->flow_date->format('l'),
                'income' => $dayIncome,
                'expense' => $dayExpense,
                'balance' => $dayBalance,
                'transaction_count' => $dayFlows->count(),
                'flows' => $dayFlows->map(function ($flow) {
                    return [
                        'id' => $flow->id,
                        'flow_type' => $flow->flow_type,
                        'total_amount' => $flow->total_amount,
                        'formatted_amount' => $flow->formatted_amount,
                        'payment_status' => $flow->payment_status,
                        'payment_status_description' => $flow->payment_status_description,
                        'payment_method' => $flow->payment_method,
                        'payment_method_description' => $flow->payment_method_description,
                        'order_number' => $flow->order_number,
                        'description' => $flow->description,
                        'account_type' => $flow->account_type,
                        'account_type_description' => $flow->account_type_description,
                        'account_id' => $flow->account_id,
                        'account' => $flow->account ? [
                            'id' => $flow->account->id,
                            'name' => $flow->account->name,
                            'type' => $flow->account->type,
                        ] : null,
                        'source_type' => $flow->source_type,
                        'source_type_description' => $flow->source_type_description,
                        'source_id' => $flow->source_id,
                        'user' => [
                            'id' => $flow->user->id,
                            'name' => $flow->user->name,
                        ],
                        'created_at' => $flow->created_at->format('H:i:s'),
                    ];
                })->values(),
            ];

            $monthlyIncome += $dayIncome;
            $monthlyExpense += $dayExpense;
            $totalTransactions += $dayFlows->count();
        }

        // Ordenar por fecha descendente
        usort($dailyStats, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $monthlyBalance = $monthlyIncome - $monthlyExpense;

        return response()->json([
            'period' => [
                'year' => $year,
                'month' => $month,
                'formatted_period' => date('F Y', strtotime("$year-$month-01")),
            ],
            'summary' => [
                'total_income' => $monthlyIncome,
                'total_expense' => $monthlyExpense,
                'monthly_balance' => $monthlyBalance,
                'total_transactions' => $totalTransactions,
                'days_with_transactions' => count($dailyStats),
            ],
            'daily_breakdown' => $dailyStats,
        ]);
    }
}

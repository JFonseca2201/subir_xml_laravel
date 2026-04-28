<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\EmployeeAdvance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeAdvanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = EmployeeAdvance::query();

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

            if (strtotime($dateFrom) && strtotime($dateTo)) {
                $query->whereBetween('advance_date', [$dateFrom, $dateTo]);
            }
        } elseif ($request->filled('date_from')) {
            $dateFrom = $request->get('date_from');
            if (strtotime($dateFrom)) {
                $query->where('advance_date', '>=', $dateFrom);
            }
        } elseif ($request->filled('date_to')) {
            $dateTo = $request->get('date_to');
            if (strtotime($dateTo)) {
                $query->where('advance_date', '<=', $dateTo);
            }
        }

        $per_page = $request->get('per_page', 10);
        $advances = $query->with(['employee', 'user', 'sucursal', 'employeePayment'])
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'total' => $advances->total(),
            'count' => $advances->count(),
            'per_page' => $advances->perPage(),
            'current_page' => $advances->currentPage(),
            'total_pages' => $advances->lastPage(),
            'from' => $advances->firstItem(),
            'to' => $advances->lastItem(),
            'has_more_pages' => $advances->hasMorePages(),
            'next_page_url' => $advances->nextPageUrl(),
            'prev_page_url' => $advances->previousPageUrl(),
            'first_page_url' => $advances->url(1),
            'last_page_url' => $advances->url($advances->lastPage()),
            'advances' => $advances->items(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'advance_date' => 'required|date',
            'concept' => 'nullable|string',
            'account_id' => 'required|integer|exists:accounts,id',
            'state' => 'nullable|integer|in:1,2,3',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
        ], [
            'employee_id.required' => 'El ID del empleado es obligatorio.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'state.in' => 'El estado debe ser 1 (Pendiente), 2 (Descontado) o 3 (Anulado).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $advance = EmployeeAdvance::create($request->only([
                'employee_id',
                'user_id',
                'amount',
                'advance_date',
                'concept',
                'state',
                'sucursale_id',
            ]));

            // NOTA: Los adelantos NO generan transacción inmediata
            // Solo se registra como deuda del empleado
            // La transacción se genera cuando se descuenta del pago

            return response()->json([
                'status' => 201,
                'message' => 'Adelanto registrado exitosamente',
                'advance' => $advance->load(['employee', 'user', 'sucursal']),
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $advance = EmployeeAdvance::with(['employee', 'user', 'sucursal', 'employeePayment'])->find($id);

        if (!$advance) {
            return response()->json([
                'status' => 404,
                'message' => 'Adelanto no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'advance' => $advance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $advance = EmployeeAdvance::find($id);

        if (!$advance) {
            return response()->json([
                'status' => 404,
                'message' => 'Adelanto no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'advance_date' => 'required|date',
            'concept' => 'nullable|string',
            'state' => 'nullable|integer|in:1,2,3',
            'employee_payment_id' => 'nullable|integer|exists:employee_payments,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
        ], [
            'employee_id.required' => 'El ID del empleado es obligatorio.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'state.in' => 'El estado debe ser 1 (Pendiente), 2 (Descontado) o 3 (Anulado).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $advance->update($request->only([
            'employee_id',
            'user_id',
            'amount',
            'advance_date',
            'concept',
            'state',
            'employee_payment_id',
            'sucursale_id',
        ]));

        return response()->json([
            'status' => 200,
            'message' => 'Adelanto actualizado exitosamente',
            'advance' => $advance->fresh(['employee', 'user', 'sucursal', 'employeePayment']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $advance = EmployeeAdvance::find($id);

        if (!$advance) {
            return response()->json([
                'status' => 404,
                'message' => 'Adelanto no encontrado',
            ], 404);
        }

        $advance->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Adelanto eliminado exitosamente',
        ]);
    }

    /**
     * Get pending advances for an employee
     */
    public function getPendingAdvances(Request $request, $employeeId)
    {
        $advances = EmployeeAdvance::where('employee_id', $employeeId)
            ->where('state', 1) // Pendientes
            ->with(['employee', 'user', 'sucursal'])
            ->orderBy('advance_date', 'asc')
            ->get();

        return response()->json([
            'status' => 200,
            'advances' => $advances,
            'total_pending' => $advances->sum('amount'),
        ]);
    }

    /**
     * Discount advances in a payment
     */
    public function discountAdvances(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_payment_id' => 'required|integer|exists:employee_payments,id',
            'advance_ids' => 'required|array',
            'advance_ids.*' => 'integer|exists:employee_advances,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $advanceIds = $request->get('advance_ids');

            // Obtener los adelantos a descontar
            $advancesToDiscount = EmployeeAdvance::whereIn('id', $advanceIds)->get();

            foreach ($advancesToDiscount as $advance) {
                // Actualizar estado del adelanto
                $advance->update([
                    'state' => 2, // Descontado
                    'employee_payment_id' => $request->get('employee_payment_id')
                ]);

                // Crear AccountTransaction para el adelanto descontado
                AccountTransaction::create([
                    'account_id' => $advance->account_id ?? 1, // Usar cuenta del adelanto o default
                    'type' => 'expense',
                    'category' => 'salary_advance',
                    'amount' => $advance->amount,
                    'description' => 'Adelanto descontado - ' . ($advance->concept ?? 'Sin concepto'),
                    'reference_id' => $advance->id,
                    'reference_type' => 'employee_advance',
                    'transaction_date' => now()->format('Y-m-d'),
                ]);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Adelantos descontados exitosamente',
                'discounted_count' => count($advanceIds),
            ]);
        });
    }
}

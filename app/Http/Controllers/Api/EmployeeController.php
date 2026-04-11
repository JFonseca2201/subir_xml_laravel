<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Employee::query();

        // Búsqueda global
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('surname', 'like', "%$search%")
                    ->orWhere('full_name', 'like', "%$search%")
                    ->orWhere('dni', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('position', 'like', "%$search%");
            });
        }

        // Filtros exactos
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->get('gender'));
        }

        if ($request->filled('sucursale_id')) {
            $query->where('sucursale_id', $request->get('sucursale_id'));
        }

        if ($request->filled('position')) {
            $query->where('position', $request->get('position'));
        }

        $per_page = $request->get('per_page', 10);
        $employees = $query->with(['user', 'sucursal'])
            ->orderBy('id', 'desc')
            ->paginate($per_page);

        return response()->json([
            'total' => $employees->total(),
            'status' => 200,
            'count' => $employees->count(),
            'per_page' => $employees->perPage(),
            'current_page' => $employees->currentPage(),
            'total_pages' => $employees->lastPage(),
            'from' => $employees->firstItem(),
            'to' => $employees->lastItem(),
            'has_more_pages' => $employees->hasMorePages(),
            'next_page_url' => $employees->nextPageUrl(),
            'prev_page_url' => $employees->previousPageUrl(),
            'first_page_url' => $employees->url(1),
            'last_page_url' => $employees->url($employees->lastPage()),
            'employees' => $employees->items(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'surname' => 'nullable|string|max:100',
            'full_name' => 'required|string|max:200',
            'dni' => 'required|string|max:15|unique:employees,dni',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:employees,email',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|integer|in:1,2,3',
            'position' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:1,2',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
        ], [
            'dni.unique' => 'La identificación ya está registrada en el sistema.',
            'email.unique' => 'El email ya está registrado en el sistema.',
            'gender.in' => 'El género debe ser 1 (Masculino), 2 (Femenino) o 3 (Otro).',
            'status.in' => 'El estado debe ser 1 (Activo) o 2 (Inactivo).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxId = Employee::max('id') ?? 0;
        DB::statement('ALTER TABLE employees AUTO_INCREMENT = ' . ($maxId + 1));

        $employee = Employee::create($request->only([
            'name',
            'surname',
            'full_name',
            'dni',
            'phone',
            'email',
            'birth_date',
            'address',
            'gender',
            'position',
            'salary',
            'hire_date',
            'account_number',
            'bank_name',
            'status',
            'user_id',
            'sucursale_id',
        ]));

        return response()->json([
            'status' => 201,
            'message' => 'Empleado creado exitosamente',
            'employee' => $employee->load(['user', 'sucursal']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $employee = Employee::with(['user', 'sucursal'])->find($id);

        if (!$employee) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'surname' => 'nullable|string|max:100',
            'full_name' => 'required|string|max:200',
            'dni' => 'required|string|max:15|unique:employees,dni,' . $id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:employees,email,' . $id,
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|integer|in:1,2,3',
            'position' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:1,2',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
        ], [
            'dni.unique' => 'El DNI ya está registrado en el sistema.',
            'email.unique' => 'El email ya está registrado en el sistema.',
            'gender.in' => 'El género debe ser 1 (Masculino), 2 (Femenino) o 3 (Otro).',
            'status.in' => 'El estado debe ser 1 (Activo) o 2 (Inactivo).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $employee->update($request->only([
            'name',
            'surname',
            'full_name',
            'dni',
            'phone',
            'email',
            'birth_date',
            'address',
            'gender',
            'position',
            'salary',
            'hire_date',
            'account_number',
            'bank_name',
            'status',
            'user_id',
            'sucursale_id',
        ]));

        return response()->json([
            'status' => 200,
            'message' => 'Empleado actualizado exitosamente',
            'employee' => $employee->fresh(['user', 'sucursal']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Empleado eliminado exitosamente',
        ]);
    }
}

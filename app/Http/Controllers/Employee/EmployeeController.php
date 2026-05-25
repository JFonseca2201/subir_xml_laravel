<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $status = $request->get('status', 'active'); // active, inactive, all
            $page = $request->get('page', 1);
            $per_page = $request->get('per_page', 10);

            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = Employee::with(['creator:id,name,email']);

            // Filtrar por estado
            if ($status === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($status === 'inactive') {
                $query->onlyTrashed();
            }

            // Búsqueda
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('identification', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            }

            $employees = $query->orderBy('created_at', 'desc')
                ->paginate($per_page, ['*'], 'page', $page);

            return response()->json([
                'status' => 200,
                'total' => $employees->total(),
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
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener los empleados',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = Auth::id();

            $employee = Employee::create($data);
            $employee->load('creator:id,name,email');

            return response()->json([
                'status' => 201,
                'message' => 'Empleado creado exitosamente',
                'employee' => $employee,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el empleado',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $employee = Employee::with(['creator:id,name,email'])
                ->withTrashed()
                ->findOrFail($id);

            return response()->json([
                'status' => 200,
                'message' => 'Empleado obtenido exitosamente',
                'employee' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
                'error' => 'El empleado con ID ' . $id . ' no existe',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeStoreRequest $request, string $id): JsonResponse
    {
        try {
            $employee = Employee::withTrashed()->findOrFail($id);
            $employee->update($request->validated());
            $employee->load('creator:id,name,email');

            return response()->json([
                'status' => 200,
                'message' => 'Empleado actualizado exitosamente',
                'employee' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
                'error' => 'El empleado con ID ' . $id . ' no existe',
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Empleado eliminado exitosamente',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado',
                'error' => 'El empleado con ID ' . $id . ' no existe',
            ], 404);
        }
    }

    /**
     * Restore a soft deleted employee.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $employee = Employee::onlyTrashed()->findOrFail($id);
            $employee->restore();
            $employee->load('creator:id,name,email');

            return response()->json([
                'status' => 200,
                'message' => 'Empleado restaurado exitosamente',
                'employee' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado o no está eliminado',
                'error' => 'El empleado con ID ' . $id . ' no existe o no está eliminado',
            ], 404);
        }
    }

    /**
     * Force delete a soft deleted employee.
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $employee = Employee::onlyTrashed()->findOrFail($id);
            $employee->forceDelete();

            return response()->json([
                'status' => 200,
                'message' => 'Empleado eliminado permanentemente',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 404,
                'message' => 'Empleado no encontrado o no está eliminado',
                'error' => 'El empleado con ID ' . $id . ' no existe o no está eliminado',
            ], 404);
        }
    }
}

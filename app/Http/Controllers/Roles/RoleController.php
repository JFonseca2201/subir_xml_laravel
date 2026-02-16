<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $roles = Role::where('name', 'like', "%{$search}%")
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at->format('Y-m-d'),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                    'permissions_pluck' => $role->permissions->pluck('name'),
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate(
                [
                    'name' => 'required|string|max:100|unique:roles,name,NULL,id,guard_name,api',
                ],
                [
                    'name.required' => 'El nombre del rol es obligatorio.',
                    'name.unique' => 'Este rol ya existe.',
                ]
            );

            $role = Role::create([
                'name' => trim($validated['name']),
                'guard_name' => 'api',
            ]);
            $permissions = $request->permissions;

            foreach ($permissions as $key => $permission) {
                $role->givePermissionTo($permission);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Rol creado con éxito.',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at->format('Y-m-d'),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                    'permissions_pluck' => $role->permissions->pluck('name'), ],
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo crear el rol.',
            ], 500);
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
    public function update(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            $validated = $request->validate(
                [
                    'name' => 'required|string|max:100|unique:roles,name,'.$role->id.',id,guard_name,api',
                ],
                [
                    'name.required' => 'El nombre del rol es obligatorio.',
                    'name.unique' => 'Ya existe otro rol con este nombre.',
                ]
            );

            $role->update([
                'name' => trim($validated['name']),
            ]);

            $permissions = $request->permissions;
            $role->syncPermissions($permissions);

            return response()->json([
                'status' => 'success',
                'message' => 'Rol actualizado con éxito.',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at->format('Y-m-d'),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                    'permissions_pluck' => $role->permissions->pluck('name'),
                ],
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo actualizar el rol.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            if ($role->users()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar el rol porque tiene usuarios asignados.',
                ], 409);
            }

            $role->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Rol eliminado con éxito.',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Rol no encontrado.',
            ], 404);

        } catch (Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar el rol.',
            ], 500);
        }
    }
}

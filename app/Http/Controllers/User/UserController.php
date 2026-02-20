<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->search;

            $users = User::with(['role', 'sucursale'])
                ->where('name', 'like', "%{$search}%")
                ->where('status', '1')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json(
                [
                    'status' => 200,
                    'users' => $users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'surname' => $user->surname,
                            'email' => $user->email,
                            'identification' => $user->identification,
                            'role_id' => $user->role_id,
                            'role' => [
                                'name' => $user->role ? $user->role->name : 'N/A',
                            ],
                            'sucursale_id' => $user->sucursale_id,
                            'sucursale' => [
                                'name' => $user->sucursale ? $user->sucursale->name : 'N/A',
                            ],
                            'gender' => $user->gender,
                            'phone' => $user->phone,
                            'address' => $user->address,
                            'avatar' => $user->avatar ? env('APP_URL') . ltrim(Storage::url($user->avatar), '/') : null,
                            'type_document' => $user->type_document,
                            'status' => $user->status,
                            'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                        ];
                    }),
                ],
                200,
            );
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'status' => 500,
                    'message' => $th->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'surname' => 'nullable|string|max:255',
                    'email' => 'required|email|max:255|unique:users,email',
                    'password' => 'required|string|min:8',
                    'phone' => 'nullable|string|max:20',
                    'sucursale_id' => 'required|string|max:20',
                    'type_document' => 'required|string|max:20',
                    'identification' => 'required|string|max:20',
                    'address' => 'nullable|string|max:500',
                    'gender' => 'required|string|max:20',
                    'status' => 'required|string|max:20',
                    'role_id' => 'required|string|max:20',
                    'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:2048',
                ],
                [
                    'avatar.image' => 'The avatar field must be an image.',
                    'avatar.mimes' => 'The avatar field must be a file of type: jpg, jpeg, png, webp.',
                ],
            );

            $maxId = User::max('id') ?? 0;
            DB::statement('ALTER TABLE users AUTO_INCREMENT = ' . ($maxId + 1));

            if ($validator->fails()) {
                return response()->json(
                    [
                        'status' => 422,
                        'errors' => $validator->errors(),
                    ],
                    422,
                );
            }

            $data = $validator->validated();
            $data['password'] = bcrypt($request->password);
            // Guardar role_id para asignarlo después
            $role_id = $data['role_id'];

            if ($request->hasFile('avatar')) {
                $path = Storage::disk('public')->put('users', $request->file('avatar'));
                $data['avatar'] = $path;
            }

            $user = User::create($data);

            if ($role_id) {
                $role = Role::findById($role_id, 'api');
                $user->assignRole($role);
            }

            return response()->json(
                [
                    'status' => 201,
                    'user' => $user,
                ],
                201,
            );
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'status' => 500,
                    'message' => $th->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = User::with(['role', 'sucursale'])->findOrFail($id);

            return response()->json(
                [
                    'status' => 200,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'surname' => $user->surname,
                        'email' => $user->email,
                        'identification' => $user->identification,
                        'role_id' => $user->role_id,
                        'role' => [
                            'name' => $user->role ? $user->role->name : 'N/A',
                        ],
                        'sucursale_id' => $user->sucursale_id,
                        'sucursale' => [
                            'name' => $user->sucursale ? $user->sucursale->name : 'N/A',
                        ],
                        'gender' => $user->gender,
                        'phone' => $user->phone,
                        'address' => $user->address,
                        'avatar' => $user->avatar ? env('APP_URL') . ltrim(Storage::url($user->avatar), '/') : null,
                        'type_document' => $user->type_document,
                        'status' => $user->status,
                        'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                    ],
                ],
                200,
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Usuario no encontrado',
                ],
                404,
            );
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'status' => 500,
                    'message' => $th->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'surname' => 'nullable|string|max:255',
                    'email' => 'required|email|max:255|unique:users,email,' . $user->id,
                    'password' => 'nullable|string|min:8',
                    'phone' => 'nullable|string|max:20',
                    'sucursale_id' => 'nullable|string|max:20',
                    'type_document' => 'required|string|max:20',
                    'identification' => 'required|string|max:20',
                    'address' => 'nullable|string|max:500',
                    'gender' => 'nullable|string|max:20',
                    'status' => 'nullable|string|max:20',
                    'role_id' => 'nullable|string|max:20',
                    'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:2048',
                ],
                [
                    'avatar.image' => 'The avatar field must be an image.',
                    'avatar.mimes' => 'The avatar field must be a file of type: jpg, jpeg, png, webp.',
                ],
            );

            if ($validator->fails()) {
                return response()->json(
                    [
                        'status' => 422,
                        'errors' => $validator->errors(),
                    ],
                    422,
                );
            }

            $data = $validator->validated();

            // Set default values for optional fields
            if (!isset($data['sucursale_id'])) {
                $data['sucursale_id'] = '1';
            }

            // Only update password if provided
            if (empty($data['password'])) {
                unset($data['password']);
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $path = Storage::disk('public')->put('users', $request->file('avatar'));
                $data['avatar'] = $path;
            }

            if (isset($data['password'])) {
                $data['password'] = bcrypt($request->password);
            }

            if (isset($data['role_id'])) {
                unset($data['role_id']);
            }

            $user->update($data);

            if ($request->role_id) {
                $role = Role::findById($request->role_id, 'api');
                $user->syncRoles([$role]);
            }

            // Reload user with relationships to get updated data
            $user->load(['role', 'sucursale']);

            return response()->json(
                [
                    'status' => 200,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'surname' => $user->surname,
                        'email' => $user->email,
                        'identification' => $user->identification,
                        'role_id' => $user->role_id,
                        'role' => [
                            'name' => $user->role ? $user->role->name : 'N/A',
                        ],
                        'sucursale_id' => $user->sucursale_id,
                        'sucursale' => [
                            'name' => $user->sucursale ? $user->sucursale->name : 'N/A',
                        ],
                        'gender' => $user->gender,
                        'phone' => $user->phone,
                        'address' => $user->address,
                        'avatar' => $user->avatar
                            ? env('APP_URL') . ltrim(Storage::url($user->avatar), '/')
                            : null,
                        'type_document' => $user->type_document,
                        'status' => $user->status,
                        'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                    ],
                ],
                200,
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Usuario no encontrado',
                ],
                404,
            );
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'status' => 500,
                    'message' => $th->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);

            // Optionally delete avatar when user is deleted
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->delete();

            return response()->json(
                [
                    'status' => 200,
                    'message' => 'Usuario eliminado correctamente',
                ],
                200,
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Usuario no encontrado',
                ],
                404,
            );
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'status' => 500,
                    'message' => $th->getMessage(),
                ],
                500,
            );
        }
    }
}

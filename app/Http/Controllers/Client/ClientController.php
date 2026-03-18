<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('surname', 'like', '%' . $search . '%')
                    ->orWhere('full_name', 'like', '%' . $search . '%')
                    ->orWhere('n_document', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('state')) {
            $query->where('state', $request->get('state'));
        }

        if ($request->has('type_client')) {
            $query->where('type_client', $request->get('type_client'));
        }

        // Paginación
        $page = $request->get('page', 1);
        $per_page = $request->get('per_page', 10);

        $clients = $query->with(['user', 'sucursal'])
            ->orderBy('id', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);

        return response()->json([
            'total' => $clients->total(),
            'count' => $clients->count(),
            'per_page' => $clients->perPage(),
            'current_page' => $clients->currentPage(),
            'total_pages' => $clients->lastPage(),
            'from' => $clients->firstItem(),
            'to' => $clients->lastItem(),
            'has_more_pages' => $clients->hasMorePages(),
            'next_page_url' => $clients->nextPageUrl(),
            'prev_page_url' => $clients->previousPageUrl(),
            'first_page_url' => $clients->url(1),
            'last_page_url' => $clients->url($clients->lastPage()),
            'clients' => $clients->items(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'full_name' => 'required|string|max:255|unique:clients',
            'phone' => 'required|string|max:20|unique:clients',
            'email' => 'nullable|email|max:255|unique:clients',
            'type_client' => 'nullable|string|max:50',
            'type_document' => 'nullable|string|max:10',
            'n_document' => 'required|string|max:20|unique:clients',
            'birth_date' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
            'state' => 'nullable|integer|in:1,2',
            'gender' => 'nullable|string|max:10',
            'ubigeo_region' => 'nullable|string|max:10',
            'ubigeo_provincia' => 'nullable|string|max:10',
            'ubigeo_ciudad' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'ciudad' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = Client::create([
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'full_name' => $request->get('full_name'),
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'type_client' => $request->get('type_client'),
            'type_document' => $request->get('type_document'),
            'n_document' => $request->get('n_document'),
            'birth_date' => $request->get('birth_date'),
            'user_id' => $request->get('user_id'),
            'sucursale_id' => $request->get('sucursale_id', 1),
            'state' => $request->get('state', 1),
            'gender' => $request->get('gender'),
            'ubigeo_region' => $request->get('ubigeo_region'),
            'ubigeo_provincia' => $request->get('ubigeo_provincia'),
            'ubigeo_distrito' => $request->get('ubigeo_distrito'),
            'region' => $request->get('region'),
            'provincia' => $request->get('provincia'),
            'distrito' => $request->get('distrito'),
            'address' => $request->get('address'),
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Cliente creado exitosamente',
            'client' => $client,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $client = Client::with(['user', 'sucursal'])->find($id);

        if (!$client) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'client' => $client,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'full_name' => 'required|string|max:255|unique:clients,full_name,' . $id,
            'phone' => 'required|string|max:20|unique:clients,phone,' . $id,
            'email' => 'nullable|email|max:255|unique:clients,email,' . $id,
            'type_client' => 'nullable|string|max:50',
            'type_document' => 'nullable|string|max:10',
            'n_document' => 'required|string|max:20|unique:clients,n_document,' . $id,
            'birth_date' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
            'state' => 'nullable|integer|in:1,2',
            'gender' => 'nullable|string|max:10',
            'ubigeo_region' => 'nullable|string|max:10',
            'ubigeo_provincia' => 'nullable|string|max:10',
            'ubigeo_ciudad' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'ciudad' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client->update([
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'full_name' => $request->get('full_name'),
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'type_client' => $request->get('type_client'),
            'type_document' => $request->get('type_document'),
            'n_document' => $request->get('n_document'),
            'birth_date' => $request->get('birth_date'),
            'user_id' => $request->get('user_id'),
            'sucursale_id' => $request->get('sucursale_id'),
            'state' => $request->get('state'),
            'gender' => $request->get('gender'),
            'ubigeo_region' => $request->get('ubigeo_region'),
            'ubigeo_provincia' => $request->get('ubigeo_provincia'),
            'ubigeo_distrito' => $request->get('ubigeo_distrito'),
            'region' => $request->get('region'),
            'provincia' => $request->get('provincia'),
            'distrito' => $request->get('distrito'),
            'address' => $request->get('address'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Cliente actualizado exitosamente',
            'client' => $client->fresh(['user', 'sucursal']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'status' => 404,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $client->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Cliente eliminado exitosamente',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Client::query();

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
     * Search clients for autocomplete or lazy loading.
     */
    public function search(Request $request)
    {
        $search = trim($request->get('q', $request->get('search', '')));

        $query = Client::select([
            'id',
            'name',
            'surname',
            'full_name',
            'n_document',
            'phone',
            'email',
        ])->where('state', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('n_document', 'like', "%{$search}%");
            });
        }

        $limit = (int) $request->get('limit', 10);
        $limit = $limit > 0 ? min(10, $limit) : 10;

        $clients = $query->orderBy('id', 'desc')->limit($limit)->get();

        return response()->json([
            'status' => 200,
            'data' => $clients,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeRequest($request);
        $exists = Client::where('n_document', $request->get('n_document'))->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El número de documento ya existe',
                'errors' => ['n_document' => 'El número de documento ya existe'],
            ], 422);
        }
        /*  $exists = Client::where('email', $request->get('email'))->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El correo electrónico ya existe',
                'errors' => ['email' => 'El correo electrónico ya existe'],
            ], 422);
        }
        $exists = Client::where('phone', $request->get('phone'))->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El número de teléfono ya existe',
                'errors' => ['phone' => 'El número de teléfono ya existe'],
            ], 422);
        } */
        // Moved full_name existence check to after fullName generation

        $validator = Validator::make($request->all(), [
            'type_client' => 'required|integer|in:1,2',
            'name' => ['exclude_if:type_client,2', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\']+$/u'],
            'surname' => ['exclude_if:type_client,2', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\']+$/u'],
            'full_name' => 'required_if:type_client,2|string|max:255|unique:clients',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => 'nullable|email|max:255',
            'type_document' => 'nullable|string|max:10',
            'n_document' => [
                'required',
                'string',
                'max:20',
                'unique:clients',
                function ($attribute, $value, $fail) use ($request) {
                    $typeDoc = (string) $request->input('type_document');
                    if ($typeDoc === '1' || $typeDoc === '2' || empty($typeDoc)) {
                        if (!preg_match('/^[0-9]+$/', $value)) {
                            $fail("El número de documento '$value' debe contener solo números.");
                            return;
                        }

                        $len = strlen($value);
                        if ($typeDoc === '1' && $len !== 10) {
                            $fail("Si el documento es Cédula, debe tener exactamente 10 dígitos.");
                            return;
                        }
                        if ($typeDoc === '2' && $len !== 13) {
                            $fail("Si el documento es RUC, debe tener exactamente 13 dígitos.");
                            return;
                        }

                        if (!$this->validateEcuadorianDocument($value)) {
                            $fail("El número de documento '$value' no es una Cédula o RUC ecuatoriano válido.");
                        }
                    } else if ($typeDoc === '3') {
                        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                            $fail("El número de pasaporte '$value' debe contener solo letras y números.");
                        }
                    }
                }
            ],
            'birth_date' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
            'state' => 'nullable|integer|in:1,2',
            'gender' => 'nullable|string|max:10',
            'ubigeo_region' => 'nullable|string|max:10',
            'ubigeo_provincia' => 'nullable|string|max:10',
            'ubigeo_distrito' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }


        // Procesar según el tipo de cliente
        $typeClient = $request->get('type_client');

        if ($typeClient == 1) {
            // Cliente final: combinar name + surname para full_name
            $fullName = trim($request->get('name') . ' ' . $request->get('surname'));
            $name = $request->get('name');
            $surname = $request->get('surname');
        } else {
            // Cliente company: usar full_name directamente
            $fullName = $request->get('full_name');
            $name = null;
            $surname = null;
        }

        // Validate uniqueness of the generated full_name
        $exists = Client::where('full_name', $fullName)->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El cliente ya existe',
                'errors' => ['full_name' => 'El cliente ya existe en otro registro'],
            ], 422);
        }

        $client = Client::create([
            'name' => $name,
            'surname' => $surname,
            'full_name' => $fullName,
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'type_client' => $typeClient,
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

        $this->normalizeRequest($request);

        $validator = Validator::make($request->all(), [
            'name' => ['exclude_if:type_client,2', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\']+$/u'],
            'surname' => ['exclude_if:type_client,2', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\']+$/u'],
            'full_name' => 'required|string|max:255|unique:clients,full_name,' . $id,
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => 'nullable|email|max:255' . $id,
            'type_client' => 'nullable|string|max:50',
            'type_document' => 'nullable|string|max:10',
            'n_document' => [
                'required',
                'string',
                'max:20',
                'unique:clients,n_document,' . $id,
                function ($attribute, $value, $fail) use ($request) {
                    $typeDoc = (string) $request->input('type_document');
                    if ($typeDoc === '1' || $typeDoc === '2' || empty($typeDoc)) {
                        if (!preg_match('/^[0-9]+$/', $value)) {
                            $fail("El número de documento '$value' debe contener solo números.");
                            return;
                        }

                        $len = strlen($value);
                        if ($typeDoc === '1' && $len !== 10) {
                            $fail("Si el documento es Cédula, debe tener exactamente 10 dígitos.");
                            return;
                        }
                        if ($typeDoc === '2' && $len !== 13) {
                            $fail("Si el documento es RUC, debe tener exactamente 13 dígitos.");
                            return;
                        }

                        if (!$this->validateEcuadorianDocument($value)) {
                            $fail("El número de documento '$value' no es una Cédula o RUC ecuatoriano válido.");
                        }
                    } else if ($typeDoc === '3') {
                        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                            $fail("El número de pasaporte '$value' debe contener solo letras y números.");
                        }
                    }
                }
            ],
            'birth_date' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'sucursale_id' => 'nullable|integer|exists:sucursales,id',
            'state' => 'nullable|integer|in:1,2',
            'gender' => 'nullable|string|max:10',
            'ubigeo_region' => 'nullable|string|max:10',
            'ubigeo_provincia' => 'nullable|string|max:10',
            'ubigeo_distrito' => 'nullable|string|max:10',
            'region' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
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

    private function normalizeRequest(Request $request)
    {
        $input = $request->all();

        // Si es compañía (type_client = 2), remover name y surname de la validación
        if (isset($input['type_client']) && (int)$input['type_client'] === 2) {
            unset($input['name']);
            unset($input['surname']);
        }

        // Si es pasaporte (3), no aplicar normalización de cédula/RUC
        $typeDoc = isset($input['type_document']) ? (string)$input['type_document'] : null;
        if ($typeDoc !== '3') {
            // Normalizar documento ecuatoriano si es aplicable
            if (isset($input['n_document'])) {
                $n_document = trim((string)$input['n_document']);
                $length = strlen($n_document);
                if ($length <= 10 && $length > 0) {
                    $n_document = str_pad($n_document, 10, "0", STR_PAD_LEFT);
                    $thirdDigit = (int) substr($n_document, 2, 1);
                    if (in_array($thirdDigit, [6, 9])) {
                        $n_document .= '001';
                        $input['type_document'] = '2'; // RUC
                    } else {
                        $input['type_document'] = isset($input['type_document']) ? (string)$input['type_document'] : '1';
                    }
                } else if ($length > 10) {
                    $n_document = str_pad($n_document, 13, "0", STR_PAD_LEFT);
                    $input['type_document'] = '2'; // RUC
                }
                $input['n_document'] = $n_document;
            }
        }

        $request->replace($input); // Reemplaza los datos del request con los normalizados
    }

    private function validateEcuadorianDocument($numero)
    {
        $numero = trim((string) $numero);
        $len = strlen($numero);

        // Debe tener 10 o 13 dígitos
        if ($len != 10 && $len != 13) {
            return false;
        }

        // Si es RUC, debe terminar en al menos un 0 y un 1 (típicamente 001 pero por seguridad validar longitud)
        if ($len == 13 && substr($numero, 10, 3) == '000') {
            return false;
        }

        $provincia = (int) substr($numero, 0, 2);
        // Validar provincia (01 a 24) o 30 (ecuatorianos en el exterior)
        if (($provincia < 1 || $provincia > 24) && $provincia != 30) {
            return false;
        }

        // Si tiene 10 dígitos, siempre se valida como Cédula (Modulo 10)
        if ($len == 10) {
            return $this->validateModulo10($numero);
        }

        // Si tiene 13 dígitos:
        // 1. Primero comprobar si los primeros 10 dígitos forman una Cédula válida (Persona Natural, incluye extranjeros con tercer dígito 6 o 9)
        if ($this->validateModulo10(substr($numero, 0, 10))) {
            return true;
        }

        $tercerDigito = (int) $numero[2];

        // 2. Si no es cédula válida y el tercer dígito es 6, RUC de entidad pública
        if ($tercerDigito == 6) {
            return $this->validateModulo11($numero, [3, 2, 7, 6, 5, 4, 3, 2], 8);
        }

        // 3. Si no es cédula válida y el tercer dígito es 9, RUC de empresa privada
        if ($tercerDigito == 9) {
            return $this->validateModulo11($numero, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9);
        }

        return false;
    }

    private function validateModulo10($cedula)
    {
        $total = 0;
        $longitud = strlen($cedula);
        if ($longitud != 10) return false;

        for ($i = 0; $i < 9; $i++) {
            $digito = (int) $cedula[$i];
            if ($i % 2 == 0) { // Posiciones impares (0, 2, 4...) se multiplican por 2
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            $total += $digito;
        }

        $decenaSuperior = (int) (ceil($total / 10) * 10);
        $digitoVerificadorCalculado = $decenaSuperior - $total;

        if ($digitoVerificadorCalculado == 10) {
            $digitoVerificadorCalculado = 0;
        }

        $digitoVerificadorReal = (int) $cedula[9];

        return $digitoVerificadorCalculado === $digitoVerificadorReal;
    }

    private function validateModulo11($ruc, $coeficientes, $posicionVerificador)
    {
        $total = 0;
        for ($i = 0; $i < count($coeficientes); $i++) {
            $total += ((int) $ruc[$i]) * $coeficientes[$i];
        }

        $residuo = $total % 11;
        $digitoVerificadorCalculado = $residuo == 0 ? 0 : 11 - $residuo;

        $digitoVerificadorReal = (int) $ruc[$posicionVerificador];

        return $digitoVerificadorCalculado === $digitoVerificadorReal;
    }
}

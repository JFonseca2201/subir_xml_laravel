<?php

namespace App\Http\Controllers;

use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExcelImportController extends Controller
{
    protected $excelImportService;
    /* 
    'name',
    'surname',
    'full_name',
    'phone',
    'email',
    'type_client',
    'type_document',
    'n_document',
    'birth_date',
    'user_id',
    'sucursale_id',
    'state',
    'gender',
    'ubigeo_region',
    'ubigeo_provincia',
    'ubigeo_distrito',
    'region',
    'provincia',
    'distrito',
    'address'
    */


    public function __construct(ExcelImportService $excelImportService)
    {
        $this->excelImportService = $excelImportService;
    }

    public function importClients(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ]);

        try {
            $user = Auth::user();
            $userId = $user ? $user->id : 1; // Default to 1 if no auth in some environments

            $result = $this->excelImportService->importClients($request->file('file'), $userId);

            return response()->json([
                'success' => true,
                'message' => 'Proceso finalizado. Filas importadas exitosamente: ' . $result['success_count'],
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Import Clients Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importVehicles(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $user = Auth::user();
            $userId = $user ? $user->id : 1;

            $result = $this->excelImportService->importVehicles($request->file('file'), $userId);

            return response()->json([
                'success' => true,
                'message' => 'Proceso finalizado. Filas importadas exitosamente: ' . $result['success_count'],
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Import Vehicles Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}

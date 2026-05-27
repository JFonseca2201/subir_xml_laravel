<?php

namespace App\Services;

use App\Models\Client\Client;
use App\Models\Vehicles\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    /**
     * Import Clients from Excel.
     */
    public function importClients(UploadedFile $file, int $userId)
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            throw new \Exception("El archivo Excel está vacío o no tiene datos válidos.");
        }

        // Obtener encabezados
        $headers = array_shift($rows);
        $headers = array_map(function ($value) {
            return trim(strtolower((string) $value));
        }, $headers);

        // Mapear columnas esperadas
        $expectedColumns = [
            'ci_ruc' => 'n_document',
            'full_name' => 'full_name',
            'direccion' => 'address',
            'email' => 'email',
            'telefono' => 'phone',
        ];

        $columnMap = [];
        foreach ($headers as $colLetter => $headerName) {
            foreach ($expectedColumns as $expected => $field) {
                if (str_contains($headerName, $expected)) {
                    $columnMap[$field] = $colLetter;
                }
            }
        }

        if (!isset($columnMap['n_document']) || !isset($columnMap['full_name'])) {
            $headersStr = implode(", ", $headers);
            throw new \Exception("El Excel no contiene las columnas requeridas. Cabeceras leídas: [$headersStr]");
        }

        $successCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1; // +1 porque sacamos el header

            $n_document = trim((string) ($row[$columnMap['n_document']] ?? ''));
            $full_name = trim((string) ($row[$columnMap['full_name']] ?? ''));
            $address = isset($columnMap['address']) ? trim((string) ($row[$columnMap['address']] ?? '')) : null;
            $email = isset($columnMap['email']) ? trim((string) ($row[$columnMap['email']] ?? '')) : null;
            $phone = isset($columnMap['phone']) ? trim((string) ($row[$columnMap['phone']] ?? '')) : null;

            if (empty($n_document) || empty($full_name)) {
                $errors[] = "Fila $rowNumber: Falta identificación o nombre.";
                continue;
            }

            // Lógica de Identificación
            $length = strlen($n_document);
            if ($length <= 10) {
                $n_document = str_pad($n_document, 10, "0", STR_PAD_LEFT);
                $type_document = 1; // Cédula
            } else {
                $n_document = str_pad($n_document, 13, "0", STR_PAD_LEFT);
                $type_document = 2; // RUC
            }

            // Validar Cédula o RUC
            if (!$this->validateEcuadorianDocument($n_document)) {
                $errors[] = "Fila $rowNumber: El número de documento '$n_document' no es una Cédula o RUC ecuatoriano válido.";
                continue;
            }

            // Lógica Tipo de Cliente: si es RUC que no es de persona natural, podría ser empresa.
            // Para simplificar: 1 (Persona Natural), 2 (Empresa)
            // Si es RUC y el 3er dígito es 6 o 9, es empresa
            $type_client = 1;
            if ($type_document == 2 && strlen($n_document) == 13) {
                $thirdDigit = (int) substr($n_document, 2, 1);
                if (in_array($thirdDigit, [6, 9])) {
                    $type_client = 2; // Empresa
                }
            }

            DB::beginTransaction();
            try {
                // Comprobar si existe para actualizar o crear nuevo
                $client = Client::where('n_document', $n_document)->first();

                if ($client) {
                    $errors[] = "Fila $rowNumber: El cliente con identificación '$n_document' ya existe y no puede ser ingresado nuevamente.";
                    DB::rollBack();
                    continue;
                }

                $client = new Client();
                $client->n_document = $n_document;
                $client->user_id = $userId;
                $client->sucursale_id = 1;
                $client->state = 1;

                $client->full_name = $full_name;
                $client->name = null;
                $client->surname = null;
                $client->address = $address ?: $client->address;
                $client->email = $email ?: $client->email;
                $client->phone = $phone ?: $client->phone;
                $client->type_document = $type_document;
                $client->type_client = $type_client;

                $client->save();
                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error importando cliente en fila $rowNumber: " . $e->getMessage());
                $errors[] = "Fila $rowNumber: Error al guardar en base de datos (" . $e->getMessage() . ").";
            }
        }

        return [
            'success_count' => $successCount,
            'errors' => $errors
        ];
    }

    /**
     * Import Vehicles from Excel.
     */
    public function importVehicles(UploadedFile $file, int $userId)
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            throw new \Exception("El archivo Excel está vacío o no tiene datos válidos.");
        }

        // Obtener encabezados
        $headers = array_shift($rows);
        $headers = array_map(function ($value) {
            return trim(strtolower((string) $value));
        }, $headers);

        // Mapear columnas esperadas
        $expectedColumns = [
            'placa' => 'license_plate',
            'marca' => 'brand',
            'modelo' => 'model',
            'tipo de vehículo' => 'vehicle_type',
            'color' => 'color',
            'año' => 'year',
        ];

        $columnMap = [];
        foreach ($headers as $colLetter => $headerName) {
            foreach ($expectedColumns as $expected => $field) {
                if (str_contains($headerName, $expected)) {
                    $columnMap[$field] = $colLetter;
                }
            }
        }

        if (!isset($columnMap['license_plate'])) {
            throw new \Exception("El Excel no contiene la columna obligatoria ('Placa').");
        }

        $successCount = 0;
        $errors = [];

        // Preparar mapas de nombres a IDs
        $brands = config('vehicle_brands', []);
        $types = config('vehicle_types', []);

        $brandMap = [];
        foreach ($brands as $id => $name) {
            $brandMap[strtolower(trim($name))] = $id;
        }

        $typeMap = [];
        foreach ($types as $id => $name) {
            $typeMap[strtolower(trim($name))] = $id;
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $license_plate = strtoupper(trim((string) ($row[$columnMap['license_plate']] ?? '')));

            $brandName = isset($columnMap['brand']) ? trim((string) ($row[$columnMap['brand']] ?? '')) : null;
            $brand = null;
            if ($brandName) {
                $brand = $brandMap[strtolower($brandName)] ?? $brandName;
            }

            $model = isset($columnMap['model']) ? trim((string) ($row[$columnMap['model']] ?? '')) : null;

            $typeName = isset($columnMap['vehicle_type']) ? trim((string) ($row[$columnMap['vehicle_type']] ?? '')) : null;
            $vehicle_type = null;
            if ($typeName) {
                $vehicle_type = $typeMap[strtolower($typeName)] ?? strtolower($typeName);
            }

            $color = isset($columnMap['color']) ? trim((string) ($row[$columnMap['color']] ?? '')) : null;

            $yearStr = isset($columnMap['year']) ? trim((string) ($row[$columnMap['year']] ?? '')) : null;
            $year = null;

            if (empty($license_plate)) {
                $errors[] = "Fila $rowNumber: Falta Placa.";
                continue;
            }

            if (!empty($yearStr)) {
                $parsedYear = (int) $yearStr;
                if ($parsedYear >= 2000 && $parsedYear <= 2025) {
                    $year = $parsedYear;
                } else {
                    $errors[] = "Fila $rowNumber: El año $yearStr está fuera de rango (2000-2025). Guardado sin año.";
                }
            }

            DB::beginTransaction();
            try {
                $vehicle = Vehicle::where('license_plate', $license_plate)->first();

                if ($vehicle) {
                    $errors[] = "Fila $rowNumber: El vehículo con placa '$license_plate' ya existe y no puede ser ingresado nuevamente.";
                    DB::rollBack();
                    continue;
                }

                $vehicle = new Vehicle();
                $vehicle->license_plate = $license_plate;
                $vehicle->user_id = $userId;
                $vehicle->status = 1;

                $vehicle->brand = $brand ?: $vehicle->brand;
                $vehicle->model = $model ?: $vehicle->model;
                $vehicle->vehicle_type = $vehicle_type ?: $vehicle->vehicle_type;
                $vehicle->color = $color ?: $vehicle->color;

                if ($year !== null) {
                    $vehicle->year = $year;
                }

                $vehicle->save();
                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error importando vehículo en fila $rowNumber: " . $e->getMessage());
                $errors[] = "Fila $rowNumber: Error al guardar en base de datos (" . $e->getMessage() . ").";
            }
        }

        return [
            'success_count' => $successCount,
            'errors' => $errors
        ];
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

        $tercerDigito = (int) $numero[2];

        // RUC de entidad pública (tercer dígito = 6)
        if ($tercerDigito == 6 && $len == 13) {
            return $this->validateModulo11($numero, [3, 2, 7, 6, 5, 4, 3, 2], 8);
        }

        // RUC de empresa privada (tercer dígito = 9)
        if ($tercerDigito == 9 && $len == 13) {
            return $this->validateModulo11($numero, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9);
        }

        // Persona natural (Cédula o RUC) (tercer dígito < 6)
        if ($tercerDigito < 6) {
            return $this->validateModulo10(substr($numero, 0, 10));
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

        $decenaSuperior = ceil($total / 10) * 10;
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

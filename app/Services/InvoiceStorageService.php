<?php

namespace App\Services;

use App\Models\Attachment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InvoiceStorageService
{
    /**
     * Determina la categoría raíz según el contexto (egresos / ingresos).
     */
    public static function resolveCategory(?string $category = null, ?Model $attachable = null, $identifier = null): string
    {
        if ($category && trim($category) !== '') {
            $cat = strtolower(trim($category));
            if (in_array($cat, ['egreso', 'egresos', 'gasto', 'gastos', 'expense', 'expenses', 'compra', 'compras'])) {
                return 'egresos';
            }
            if (in_array($cat, ['ingreso', 'ingresos', 'venta', 'ventas', 'income', 'incomes', 'factura', 'facturas', 'facturas_generadas'])) {
                return 'ingresos';
            }
            return self::sanitizeDirectorySegment($cat);
        }

        if ($attachable) {
            $class = get_class($attachable);

            // FinanceRecord: Diferenciar según type
            if ($attachable instanceof \App\Models\Finance\FinanceRecord) {
                return $attachable->type == \App\Models\Finance\FinanceRecord::TYPE_EXPENSE ? 'egresos' : 'ingresos';
            }

            // Transferencias (Movimientos internos / salidas)
            if ($attachable instanceof \App\Models\Finance\InternalTransfer) {
                return 'egresos';
            }

            // Gastos de empleado, compras, proveedores
            if (str_contains($class, 'EmployeeExpense') || str_contains($class, 'Purchase') || str_contains($class, 'Supplier')) {
                return 'egresos';
            }

            // Ventas, WorkOrders, Aportes, Clientes
            if (str_contains($class, 'Sale') || str_contains($class, 'WorkOrder') || str_contains($class, 'Contribution') || str_contains($class, 'Aporte') || str_contains($class, 'Client')) {
                return 'ingresos';
            }
        }

        // Si tenemos un identificador como EGR-xxx o ING-xxx
        if ($identifier) {
            $idStr = strtoupper((string)$identifier);
            if (str_starts_with($idStr, 'EGR') || str_starts_with($idStr, 'GASTO') || str_starts_with($idStr, 'EXP') || str_starts_with($idStr, 'COMPRA') || str_starts_with($idStr, 'PROV') || str_starts_with($idStr, 'TRANS')) {
                return 'egresos';
            }
            if (str_starts_with($idStr, 'ING') || str_starts_with($idStr, 'OT') || str_starts_with($idStr, 'FAC') || str_starts_with($idStr, 'VENTA') || str_starts_with($idStr, 'APORTE')) {
                return 'ingresos';
            }
        }

        return 'ingresos';
    }

    /**
     * Define la ruta jerárquica de carpetas dividida por Ingresos y Egresos:
     * - Egresos: egresos/{Año}/{Mes_Nombre}/{Día_Nombre}
     * - Ingresos: ingresos/{Año}/{Mes_Nombre}/{Día_Nombre}
     */
    public static function getTargetDirectory(?Carbon $date = null, ?string $category = null, ?Model $attachable = null, $identifier = null): string
    {
        $date = $date ? $date->copy() : Carbon::now();
        $date->locale('es');

        $resolvedCategory = self::resolveCategory($category, $attachable, $identifier);
        $year = $date->format('Y');
        $month = $date->translatedFormat('m_F'); // ej. 08_agosto
        $day = $date->translatedFormat('d_l');   // ej. 22_sabado

        // Sanitizar nombres de carpetas para evitar caracteres extraños en cualquier OS
        $month = self::sanitizeDirectorySegment($month);
        $day = self::sanitizeDirectorySegment($day);

        return "{$resolvedCategory}/{$year}/{$month}/{$day}";
    }

    /**
     * Limpia nombres de carpetas de acentos
     */
    public static function sanitizeDirectorySegment(string $segment): string
    {
        $clean = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $segment);
        if ($clean === false || empty($clean)) {
            $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $segment);
        }
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $clean));
        return $clean ?: 'general';
    }

    /**
     * Limpia el nombre del cliente o tercero para nombres de archivo seguros y limpios
     */
    public static function sanitizeName(?string $name): string
    {
        if (!$name || trim($name) === '') {
            return 'GENERAL';
        }

        // Transliterar caracteres acentuados
        $clean = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($clean === false || empty($clean)) {
            $clean = preg_replace('/[áäàâã]/iu', 'a', $name);
            $clean = preg_replace('/[éëèê]/iu', 'e', $clean);
            $clean = preg_replace('/[íïìî]/iu', 'i', $clean);
            $clean = preg_replace('/[óöòôõ]/iu', 'o', $clean);
            $clean = preg_replace('/[úüùû]/iu', 'u', $clean);
            $clean = preg_replace('/[ñ]/iu', 'n', $clean);
        }

        // Eliminar caracteres especiales preservando alfanuméricos y espacios
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return !empty($clean) ? strtoupper($clean) : 'GENERAL';
    }

    /**
     * Genera el nombre del archivo de comprobante respetando la regla estricta:
     * - Un comprobante: {ID} {NOMBRE}.ext
     * - Múltiples comprobantes: {ID} {NOMBRE}_001.ext, {ID} {NOMBRE}_002.ext
     */
    public static function formatReceiptFileName($identifier, string $cleanName, int $index, int $total, string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));

        if ($total > 1) {
            $suffix = '_' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT); // _001, _002...
            return "{$identifier} {$cleanName}{$suffix}.{$ext}";
        }

        return "{$identifier} {$cleanName}.{$ext}";
    }

    /**
     * Guarda el PDF principal y los comprobantes de pago de una transacción, registrando los Attachments si se provee el modelo.
     *
     * @param int|string $orderNumber  Ej: 1600 o "OT-0001600"
     * @param string $clientName       Ej: "Juan Perez"
     * @param string|null $pdfBinaryContent Contenido binario del PDF
     * @param array $receiptFiles      Array de archivos subidos (UploadedFile[])
     * @param Carbon|null $date        Fecha de la transacción
     * @param Model|null $attachable   Instancia del modelo (Sale, WorkOrder, etc.)
     * @param string|null $category    Categoría explícita ('ingresos' o 'egresos')
     */
    public function saveInvoiceAndReceipts(
        $orderNumber,
        string $clientName,
        $pdfBinaryContent = null,
        array $receiptFiles = [],
        ?Carbon $date = null,
        ?Model $attachable = null,
        ?string $category = null
    ): array {
        $date = $date ?? Carbon::now();
        $baseDir = self::getTargetDirectory($date, $category, $attachable, $orderNumber);
        $cleanClient = self::sanitizeName($clientName);

        $savedData = [
            'directory' => $baseDir,
            'invoice_pdf' => null,
            'receipts' => [],
            'attachments' => [],
        ];

        // 1. Guardar Factura / Documento PDF -> {ID} {CLIENTE}.pdf
        if ($pdfBinaryContent !== null && !empty($pdfBinaryContent)) {
            $pdfFileName = "{$orderNumber} {$cleanClient}.pdf";
            $pdfFullPath = "{$baseDir}/{$pdfFileName}";

            Storage::disk('public')->put($pdfFullPath, $pdfBinaryContent);
            $savedData['invoice_pdf'] = $pdfFullPath;

            if ($attachable) {
                $attachment = Attachment::create([
                    'attachable_type' => get_class($attachable),
                    'attachable_id' => $attachable->getKey(),
                    'file_path' => $pdfFullPath,
                    'file_name' => $pdfFileName,
                    'original_name' => $pdfFileName,
                    'mime_type' => 'application/pdf',
                    'file_size' => strlen($pdfBinaryContent),
                    'type' => 'invoice_pdf',
                ]);
                $savedData['attachments'][] = $attachment;
            }
        }

        // 2. Guardar Comprobantes de pago adjuntos
        if (!empty($receiptFiles)) {
            $receiptAttachments = $this->attachReceiptsToModel(
                $attachable,
                $orderNumber,
                $cleanClient,
                $receiptFiles,
                $date,
                'receipt',
                $category
            );

            foreach ($receiptAttachments as $att) {
                $savedData['receipts'][] = $att instanceof Attachment ? $att->file_path : $att;
                if ($att instanceof Attachment) {
                    $savedData['attachments'][] = $att;
                }
            }
        }

        return $savedData;
    }

    /**
     * Adjunta comprobantes de pago a cualquier modelo Eloquent con la estructura jerárquica y nomenclatura estricta.
     *
     * @param Model|null $model
     * @param int|string $identifier
     * @param string $partyName
     * @param array $receiptFiles
     * @param Carbon|null $date
     * @param string $type
     * @param string|null $category
     * @return array
     */
    public function attachReceiptsToModel(
        ?Model $model,
        $identifier,
        string $partyName,
        array $receiptFiles,
        ?Carbon $date = null,
        string $type = 'receipt',
        ?string $category = null
    ): array {
        $date = $date ?? Carbon::now();
        $baseDir = self::getTargetDirectory($date, $category, $model, $identifier);
        $cleanParty = self::sanitizeName($partyName);
        $totalReceipts = count($receiptFiles);
        $results = [];

        // Asegurar que el directorio exista
        if (!Storage::disk('public')->exists($baseDir)) {
            Storage::disk('public')->makeDirectory($baseDir);
        }

        // Contar si ya existen comprobantes previos en la BD para este modelo y mantener correlativo secuencial
        $existingCount = 0;
        if ($model) {
            $existingCount = Attachment::where('attachable_type', get_class($model))
                ->where('attachable_id', $model->getKey())
                ->where('type', $type)
                ->count();
        }

        $effectiveTotal = $totalReceipts + $existingCount;

        foreach ($receiptFiles as $index => $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }

            $extension = $file->getClientOriginalExtension() ?: 'png';
            $fileIndex = $existingCount + $index;

            $fileName = self::formatReceiptFileName(
                $identifier,
                $cleanParty,
                $fileIndex,
                $effectiveTotal,
                $extension
            );

            $filePath = Storage::disk('public')->putFileAs($baseDir, $file, $fileName);
            $fullPath = "{$baseDir}/{$fileName}";

            if ($model) {
                $attachment = Attachment::create([
                    'attachable_type' => get_class($model),
                    'attachable_id' => $model->getKey(),
                    'file_path' => $fullPath,
                    'file_name' => $fileName,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'type' => $type,
                    'metadata' => [
                        'uploaded_at' => Carbon::now()->toIso8601String(),
                        'client_or_party' => $cleanParty,
                        'identifier' => $identifier,
                        'category' => self::resolveCategory($category, $model, $identifier),
                    ],
                ]);
                $results[] = $attachment;
            } else {
                $results[] = $fullPath;
            }
        }

        return $results;
    }

    /**
     * Guarda el PDF de un modelo y lo registra como Attachment.
     */
    public function savePdfForModel(
        Model $model,
        $identifier,
        string $partyName,
        string $pdfBinaryContent,
        ?Carbon $date = null,
        string $type = 'invoice_pdf',
        ?string $category = null
    ): Attachment {
        $date = $date ?? Carbon::now();
        $baseDir = self::getTargetDirectory($date, $category, $model, $identifier);
        $cleanParty = self::sanitizeName($partyName);

        $pdfFileName = "{$identifier} {$cleanParty}.pdf";
        $pdfFullPath = "{$baseDir}/{$pdfFileName}";

        Storage::disk('public')->put($pdfFullPath, $pdfBinaryContent);

        return Attachment::updateOrCreate(
            [
                'attachable_type' => get_class($model),
                'attachable_id' => $model->getKey(),
                'type' => $type,
            ],
            [
                'file_path' => $pdfFullPath,
                'file_name' => $pdfFileName,
                'original_name' => $pdfFileName,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($pdfBinaryContent),
                'metadata' => [
                    'generated_at' => Carbon::now()->toIso8601String(),
                    'identifier' => $identifier,
                    'category' => self::resolveCategory($category, $model, $identifier),
                ],
            ]
        );
    }
}

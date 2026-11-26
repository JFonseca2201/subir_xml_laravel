<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCatalogPdfJob;
use App\Models\Supplier\DistributorCatalog;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DistributorCatalogPdfController extends Controller
{
    /**
     * Subir catálogo PDF e iniciar extracción de texto en archivo independiente
     */
    public function upload(Request $request): JsonResponse
    {
        // 1. Validar parámetros requeridos
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required',
            'title'       => 'nullable|string|max:255',
            'file'        => 'required|file|max:102400', // Máx 100MB
        ], [
            'supplier_id.required' => 'Debe seleccionar un proveedor o distribuidor.',
            'file.required'        => 'Debe adjuntar el archivo PDF del catálogo.',
            'file.max'             => 'El archivo excede el tamaño máximo permitido (100 MB).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'success' => false,
                'message' => 'Validación fallida: ' . implode(', ', $validator->errors()->all()),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validar extensión PDF de forma tolerante (mimetype o extensión de archivo)
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
            return response()->json([
                'status'  => 422,
                'success' => false,
                'message' => 'Solo se permiten archivos en formato PDF.',
            ], 422);
        }

        try {
            // 2. Garantizar que la tabla distributor_catalogs y sus columnas existan en la BD
            $this->ensureCatalogTableExists();

            // 3. Resolver Distribuidor de forma segura sin excepciones 404/500
            $rawSupplierId = $request->input('supplier_id');
            $supplierId = (is_numeric($rawSupplierId) && (int) $rawSupplierId > 0) ? (int) $rawSupplierId : null;
            $supplierName = 'Distribuidor General';

            if ($supplierId) {
                $supplier = Supplier::find($supplierId);
                if ($supplier) {
                    $supplierName = $supplier->name;
                } else {
                    $supplierName = $request->input('supplier_name') ?: "Distribuidor #{$supplierId}";
                }
            } else {
                $supplierName = $request->input('supplier_name') ?: 'Distribuidor General';
            }

            // 4. Preparar almacenamiento físico en storage/app/public/catalogs/{supplier_id}
            $originalFilename = $file->getClientOriginalName();
            $title = $request->input('title') ?: pathinfo($originalFilename, PATHINFO_FILENAME);

            $folder = $supplierId ? "catalogs/{$supplierId}" : "catalogs/general";
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder);
            }
            if (!Storage::disk('public')->exists('catalogs/indexes')) {
                Storage::disk('public')->makeDirectory('catalogs/indexes');
            }

            $path = $file->store($folder, 'public');

            // 5. Guardar ÚNICAMENTE el registro ligero de metadatos en la base de datos
            $catalog = DistributorCatalog::create([
                'supplier_id'       => $supplierId,
                'supplier_name'     => mb_substr($supplierName, 0, 150),
                'title'             => mb_substr($title, 0, 250),
                'file_name'         => mb_substr($originalFilename, 0, 250),
                'original_filename' => mb_substr($originalFilename, 0, 250),
                'file_path'         => $path,
                'file_size'         => $file->getSize() ?: 0,
                'status'            => 'pending',
            ]);

            // 6. Ejecutar extracción de texto plano (usando dispatchAfterResponse para responder de inmediato)
            try {
                ProcessCatalogPdfJob::dispatchAfterResponse($catalog->id);
            } catch (Throwable $qe) {
                Log::warning("dispatchAfterResponse no disponible, ejecutando en background o sync: " . $qe->getMessage());
                try {
                    ProcessCatalogPdfJob::dispatchSync($catalog->id);
                } catch (Throwable $se) {
                    Log::error("Error en extracción sincrónica de PDF: " . $se->getMessage());
                    $catalog->update([
                        'status'        => 'failed',
                        'error_message' => mb_substr($se->getMessage(), 0, 1000),
                    ]);
                }
            }

            return response()->json([
                'status'  => 201,
                'success' => true,
                'message' => 'Catálogo PDF subido exitosamente. Indexación de texto plano en proceso.',
                'catalog' => $catalog,
            ], 201);
        } catch (Throwable $e) {
            Log::error("Error crítico en upload de catálogo PDF: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error al subir catálogo PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda en los archivos de texto/índices independientes de los catálogos PDF
     * La base de datos no contiene el texto; este método consulta el índice en storage.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $this->ensureCatalogTableExists();

            $searchTerm = trim((string) $request->get('search', ''));
            $supplierId = $request->get('supplier_id');
            $page = max(1, (int) $request->get('page', 1));
            $perPage = max(5, min(100, (int) $request->get('per_page', 20)));

            if (mb_strlen($searchTerm) < 2) {
                return response()->json([
                    'status'       => 200,
                    'success'      => true,
                    'data'         => [],
                    'total'        => 0,
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $perPage,
                ]);
            }

            // Obtener únicamente los catálogos completados desde la base de datos (metadatos)
            $catalogQuery = DistributorCatalog::query()->where('status', 'completed');

            if ($supplierId && $supplierId !== 'all') {
                $catalogQuery->where('supplier_id', $supplierId);
            }

            $catalogs = $catalogQuery->orderBy('supplier_name', 'asc')->get();

            $tokens = array_filter(
                explode(' ', mb_strtolower($searchTerm)),
                fn($t) => mb_strlen(trim($t)) >= 2
            );

            if (empty($tokens)) {
                $tokens = [mb_strtolower($searchTerm)];
            }

            $allMatches = [];

            // Buscar dentro de los archivos de índice independientes en disco
            foreach ($catalogs as $catalog) {
                if (!$catalog->index_path || !Storage::disk('public')->exists($catalog->index_path)) {
                    continue;
                }

                $jsonContent = Storage::disk('public')->get($catalog->index_path);
                $indexData = json_decode($jsonContent, true);

                if (!isset($indexData['pages']) || !is_array($indexData['pages'])) {
                    continue;
                }

                foreach ($indexData['pages'] as $pageItem) {
                    $pageText = $pageItem['text'] ?? '';
                    if (empty($pageText)) {
                        continue;
                    }

                    $lowerPageText = mb_strtolower($pageText);

                    // Verificar coincidencia de todos los tokens buscados
                    $matchesAll = true;
                    foreach ($tokens as $token) {
                        if (mb_strpos($lowerPageText, $token) === false) {
                            $matchesAll = false;
                            break;
                        }
                    }

                    if ($matchesAll) {
                        $snippetData = $this->generateSnippetLines($pageText, $searchTerm);
                        $fileUrl = $catalog->file_url;
                        $pageNumber = $pageItem['page'];
                        // Usar la línea con mayor coincidencia para extraer el precio de referencia exacto
                        $price = $this->detectPrice($snippetData['best_line'] ?: $pageText, $searchTerm);
                        // Extraer código de producto / referencia / SKU de la mejor línea coincidente
                        $code = $this->extractProductCode($snippetData['best_line'] ?: $pageText);

                        $allMatches[] = [
                            'id'            => "cat_{$catalog->id}_p_{$pageNumber}",
                            'catalog_id'    => $catalog->id,
                            'supplier_id'   => $catalog->supplier_id,
                            'supplier_name' => $catalog->supplier_name,
                            'catalog_title' => $catalog->title ?: $catalog->file_name,
                            'file_name'     => $catalog->file_name,
                            'page_number'   => $pageNumber,
                            'code'          => $code,
                            'price'         => $price,
                            'snippet'       => $snippetData['snippet'],
                            'pdf_url'       => "{$fileUrl}#page={$pageNumber}",
                            'created_at'    => $catalog->created_at?->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }

            $total = count($allMatches);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;
            $items = array_slice($allMatches, $offset, $perPage);

            return response()->json([
                'status'       => 200,
                'success'      => true,
                'data'         => $items,
                'total'        => $total,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'query'        => $searchTerm,
            ]);
        } catch (Throwable $e) {
            Log::error("Error al buscar en catálogos PDF: " . $e->getMessage());

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error al buscar en catálogos PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listado de catálogos PDF registrados (solo metadatos)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->ensureCatalogTableExists();

            $query = DistributorCatalog::with('supplier:id,name,ruc');

            if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $catalogs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status'  => 200,
                'success' => true,
                'data'    => $catalogs,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error al obtener catálogos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar el estado del procesamiento de un catálogo
     */
    public function getStatus(int $id): JsonResponse
    {
        try {
            $this->ensureCatalogTableExists();
            $catalog = DistributorCatalog::findOrFail($id);

            return response()->json([
                'status'         => 200,
                'success'        => true,
                'catalog_id'     => $catalog->id,
                'catalog_status' => $catalog->status,
                'total_pages'    => $catalog->total_pages,
                'error_message'  => $catalog->error_message,
                'created_at'     => $catalog->created_at,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 404,
                'success' => false,
                'message' => 'Catálogo no encontrado.',
            ], 404);
        }
    }

    /**
     * Visualizar archivo PDF directamente en el navegador con soporte inline
     */
    public function viewFile(int $id)
    {
        $this->ensureCatalogTableExists();
        $catalog = DistributorCatalog::findOrFail($id);

        if (!$catalog->file_path || !Storage::disk('public')->exists($catalog->file_path)) {
            abort(404, 'Archivo PDF no encontrado en el servidor.');
        }

        $fullPath = Storage::disk('public')->path($catalog->file_path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($catalog->file_name ?: 'catalogo.pdf') . '"',
        ]);
    }

    /**
     * Eliminar catálogo, su archivo PDF físico y su índice de texto independiente
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->ensureCatalogTableExists();
            $catalog = DistributorCatalog::findOrFail($id);

            // Eliminar archivo PDF físico
            if ($catalog->file_path && Storage::disk('public')->exists($catalog->file_path)) {
                Storage::disk('public')->delete($catalog->file_path);
            }

            // Eliminar índice de texto independiente en json y txt
            if ($catalog->index_path && Storage::disk('public')->exists($catalog->index_path)) {
                Storage::disk('public')->delete($catalog->index_path);
            }

            $txtPath = "catalogs/indexes/catalog_{$catalog->id}_text.txt";
            if (Storage::disk('public')->exists($txtPath)) {
                Storage::disk('public')->delete($txtPath);
            }

            $catalog->delete();

            return response()->json([
                'status'  => 200,
                'success' => true,
                'message' => 'Catálogo PDF y su índice de texto eliminados correctamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error al eliminar catálogo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asegura de manera dinámica la existencia de la tabla distributor_catalogs y cada una de sus columnas,
     * evitando caídas si la tabla ya existía en MySQL con una estructura antigua o incompleta.
     */
    private function ensureCatalogTableExists(): void
    {
        if (!Schema::hasTable('distributor_catalogs')) {
            Schema::create('distributor_catalogs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->string('supplier_name', 150)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->string('file_path', 500)->nullable();
                $table->string('index_path', 500)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedInteger('total_pages')->default(0);
                $table->string('status', 50)->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
            return;
        }

        // Si la tabla ya existe, verificar y agregar individualmente cualquier columna faltante
        $columnsToAdd = [
            'supplier_id'       => fn(Blueprint $t) => $t->unsignedBigInteger('supplier_id')->nullable()->index(),
            'supplier_name'     => fn(Blueprint $t) => $t->string('supplier_name', 150)->nullable()->index(),
            'title'             => fn(Blueprint $t) => $t->string('title', 255)->nullable(),
            'file_name'         => fn(Blueprint $t) => $t->string('file_name', 255)->nullable(),
            'original_filename' => fn(Blueprint $t) => $t->string('original_filename', 255)->nullable(),
            'file_path'         => fn(Blueprint $t) => $t->string('file_path', 500)->nullable(),
            'index_path'        => fn(Blueprint $t) => $t->string('index_path', 500)->nullable(),
            'file_size'         => fn(Blueprint $t) => $t->unsignedBigInteger('file_size')->default(0),
            'total_pages'       => fn(Blueprint $t) => $t->unsignedInteger('total_pages')->default(0),
            'status'            => fn(Blueprint $t) => $t->string('status', 50)->default('pending')->index(),
            'error_message'     => fn(Blueprint $t) => $t->text('error_message')->nullable(),
        ];

        foreach ($columnsToAdd as $colName => $definition) {
            if (!Schema::hasColumn('distributor_catalogs', $colName)) {
                try {
                    Schema::table('distributor_catalogs', function (Blueprint $t) use ($definition) {
                        $definition($t);
                    });
                } catch (Throwable $th) {
                    Log::warning("No se pudo agregar columna {$colName} a distributor_catalogs: " . $th->getMessage());
                }
            }
        }
    }

    /**
     * Extrae un fragmento de texto legible limpio centrado en la coincidencia
     */
    /**
     * Genera un fragmento de texto con líneas separadas respetando los saltos de línea del PDF:
     * - Cada línea con coincidencias se muestra en su propia fila independiente.
     * - Si una misma línea tiene varias coincidencias (ej: 195, 55 y 15), la línea solo se muestra una vez.
     * - Si hay más líneas con coincidencias, se presentan con saltos de línea y separadores visuales.
     */
    private function generateSnippetLines(string $content, string $searchTerm, int $maxLines = 6): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $content);
        $cleanLines = [];
        foreach ($rawLines as $l) {
            $trimmed = trim(preg_replace('/[ \t]+/', ' ', $l));
            if (!empty($trimmed)) {
                $cleanLines[] = $trimmed;
            }
        }

        if (empty($cleanLines)) {
            return ['snippet' => '', 'best_line' => ''];
        }

        $tokens = array_filter(
            explode(' ', mb_strtolower(trim($searchTerm))),
            fn($t) => mb_strlen(trim($t)) >= 2
        );

        if (empty($tokens)) {
            $tokens = [mb_strtolower(trim($searchTerm))];
        }

        $scoredLines = [];
        $maxMatchedTokens = 0;

        foreach ($cleanLines as $idx => $line) {
            $lowerLine = mb_strtolower($line);
            $score = 0;
            $matchedTokens = 0;

            foreach ($tokens as $token) {
                if (mb_strpos($lowerLine, $token) !== false) {
                    $matchedTokens++;
                    $score += 20;
                }
            }

            // Bonus si la frase de búsqueda completa aparece textualmente
            if (mb_strpos($lowerLine, mb_strtolower(trim($searchTerm))) !== false) {
                $score += 60;
            }

            if ($matchedTokens > 0) {
                if ($matchedTokens > $maxMatchedTokens) {
                    $maxMatchedTokens = $matchedTokens;
                }
                $scoredLines[] = [
                    'idx'           => $idx,
                    'line'          => $line,
                    'score'         => $score,
                    'matchedTokens' => $matchedTokens,
                ];
            }
        }

        if (empty($scoredLines)) {
            $firstLines = array_slice($cleanLines, 0, 3);
            $safe = array_map(fn($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $firstLines);
            return [
                'snippet'   => implode('<br>', $safe),
                'best_line' => $cleanLines[0] ?? ''
            ];
        }

        // Ordenar para determinar la mejor línea (para extracción de precio de referencia)
        $sortedByScore = $scoredLines;
        usort($sortedByScore, fn($a, $b) => $b['score'] <=> $a['score']);
        $bestLine = $sortedByScore[0]['line'] ?? '';

        // Filtrar líneas relevantes según el número de tokens coincidentes
        $threshold = ($maxMatchedTokens >= 3) ? max(2, $maxMatchedTokens - 1) : max(1, $maxMatchedTokens);
        $selected = array_filter($scoredLines, fn($item) => $item['matchedTokens'] >= $threshold);

        if (empty($selected)) {
            $selected = $scoredLines;
        }

        // Deduplicación de líneas idénticas (evita repetir líneas duplicadas en el PDF)
        $uniqueLines = [];
        $seen = [];
        foreach ($selected as $item) {
            if (!isset($seen[$item['line']])) {
                $seen[$item['line']] = true;
                $uniqueLines[] = $item;
            }
        }

        $totalMatchesCount = count($uniqueLines);

        // Limitar al máximo de líneas deseadas
        $moreCount = 0;
        if ($totalMatchesCount > $maxLines) {
            $moreCount = $totalMatchesCount - $maxLines;
            // Tomar las mejores puntuadas
            usort($uniqueLines, fn($a, $b) => $b['score'] <=> $a['score']);
            $uniqueLines = array_slice($uniqueLines, 0, $maxLines);
            // Volver a ordenar por su orden original de aparición en la página
            usort($uniqueLines, fn($a, $b) => $a['idx'] <=> $b['idx']);
        }

        // Resaltar términos de búsqueda en cada línea individual
        $escapedWords = array_map(fn($w) => preg_quote(trim($w), '/'), $tokens);
        $pattern = '/(' . implode('|', $escapedWords) . ')/iu';

        $formattedLines = [];
        foreach ($uniqueLines as $item) {
            $safe = htmlspecialchars($item['line'], ENT_QUOTES, 'UTF-8');
            $highlighted = preg_replace($pattern, '<span class="catalog-highlight">$1</span>', $safe);
            $formattedLines[] = '<div class="pdf-snippet-line">' . $highlighted . '</div>';
        }

        if ($moreCount > 0) {
            $formattedLines[] = '<div class="pdf-snippet-more">... (+ ' . $moreCount . ' líneas coincidentes más en esta pág.)</div>';
        }

        return [
            'snippet'   => implode("\n", $formattedLines),
            'best_line' => $bestLine
        ];
    }

    private function generateSnippet(string $content, string $searchTerm, int $contextChars = 140): string
    {
        $res = $this->generateSnippetLines($content, $searchTerm);
        return $res['snippet'];
    }

    /**
     * Extrae de forma inteligente el código de producto, referencia, medida o SKU de la línea del catálogo:
     * - Detecta medidas de neumáticos estándar (ej: 185/60 R14, 195/55 R15, 12 R22.5)
     * - Detecta códigos con guiones típicos de repuestos automotrices (ej: 5497136-TRA, 03-005-05-0005)
     * - Detecta prefijos explícitos (COD, SKU, REF, ART)
     * - Detecta códigos alfanuméricos iniciales (ej: 1138MM, 1040, 9250582)
     */
    private function extractProductCode(string $line): ?string
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $ignoreWords = [
            'DESCRIPCION', 'PRECIO', 'STOCK', 'P.V.D.', 'INC.IVA.', 'LISTADO', 'NUESTROS',
            'OCTUBRE', 'CODIGO', 'ARTICULO', 'MARCA', 'PAGINA', 'USUARIO', 'FECHA'
        ];

        // 1. Medidas de neumáticos / llantas (ej: 185 60 R 14, 195/55 R 15, LT 235 75 R 15)
        if (preg_match('/(?:^|\s)(?:LT\s*)?([0-9]{3})\s*(?:\/|\s)\s*([0-9]{2})\s*R\s*([0-9]{2}(?:\.[0-9])?)/i', $line, $m)) {
            return "{$m[1]}/{$m[2]} R{$m[3]}";
        }
        if (preg_match('/(?:^|\s)(?:LT\s*)?([0-9]{1,2}(?:\.[0-9]+)?)\s*R\s*([0-9]{2}(?:\.[0-9])?)/i', $line, $m)) {
            return "{$m[1]} R{$m[2]}";
        }

        // 2. Prefijos explícitos de código (ej: COD: 12345, SKU: 34963MM, REF: 5543)
        if (preg_match('/(?:COD|CODIGO|CÓDIGO|SKU|REF|ART|ITEM)\.?\s*:?\s*([A-Z0-9\-_]{3,20})/i', $line, $m)) {
            return strtoupper($m[1]);
        }

        // 3. Códigos de repuestos con guiones (ej: 03-005-05-0005, 5497136-TRA, AH254738-IND)
        if (preg_match('/^([A-Z0-9]{2,8}(?:-[A-Z0-9]{2,8})+)/i', $line, $m)) {
            return strtoupper($m[1]);
        }

        // 4. Primer token alfanumérico que actúa como código/referencia (ej: 1138MM, 1040, 9250582)
        if (preg_match('/^([A-Z0-9\-_]{3,15})\s+/i', $line, $m)) {
            $candidate = strtoupper($m[1]);
            if (!in_array($candidate, $ignoreWords) && !preg_match('/^(LLANTA|ACEITE|FILTRO|PASTILLA|DISCO|AMORTIGUADOR|BUJIA|BATERIA)$/i', $candidate)) {
                if (preg_match('/[0-9]/', $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza y parsea cualquier formato numérico de precio:
     * - Con miles y decimales en formato inglés (1,500.00 / 12,450.50)
     * - Con miles y decimales en formato español/europeo (1.500,00 / 12.450,50)
     * - Decimales simples con coma o punto (23,88 / 23.88 / 0.50)
     */
    private function parsePriceValue(string $raw): ?float
    {
        $clean = trim($raw);
        $clean = preg_replace('/[^\d,\.]/', '', $clean);
        if (empty($clean)) {
            return null;
        }

        // Caso 1: Contiene punto y coma simultáneamente (ej: 1,500.50 o 1.500,50)
        if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');
            if ($lastDot > $lastComma) {
                // Formato inglés: coma es separador de miles (1,500.50)
                $clean = str_replace(',', '', $clean);
            } else {
                // Formato hispano: punto es miles, coma es decimal (1.500,50)
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }
        } elseif (strpos($clean, ',') !== false) {
            // Solo coma
            $parts = explode(',', $clean);
            if (count($parts) === 2 && strlen($parts[1]) === 2) {
                // Decimal típico: 23,88
                $clean = $parts[0] . '.' . $parts[1];
            } else {
                // Miles sin decimales: 1,500
                $clean = str_replace(',', '', $clean);
            }
        }

        $val = (float) $clean;

        return ($val >= 0.10 && $val <= 99999) ? $val : null;
    }

    /**
     * Extracción flexible de precios en cualquier posición (izquierda, derecha o en línea):
     * - Detecta símbolos de moneda ($ o USD / US$) antes o después del número
     * - Detecta patrones con miles y decimales (0.00, 1,500.00, 1.500,00)
     * - Evalúa una ventana amplia alrededor de las palabras buscadas y pondera cercanía y etiquetas
     */
    private function detectPrice(string $content, string $searchTerm): ?string
    {
        if (empty($content) || empty($searchTerm)) {
            return null;
        }

        $cleanContent = preg_replace('/\s+/', ' ', trim($content));
        $lowerContent = mb_strtolower($cleanContent);
        $lowerTerm = mb_strtolower(trim($searchTerm));

        // Ubicar la posición del término completo o de las palabras clave
        $pos = mb_strpos($lowerContent, $lowerTerm);
        if ($pos === false) {
            $words = array_filter(explode(' ', $lowerTerm), fn($w) => mb_strlen($w) >= 2);
            foreach ($words as $word) {
                $pos = mb_strpos($lowerContent, $word);
                if ($pos !== false) {
                    break;
                }
            }
        }

        if ($pos === false) {
            $pos = 0;
        }

        // Ventana generosa de búsqueda: 320 caracteres antes y 320 después
        $start = max(0, $pos - 320);
        $length = 640;
        $window = mb_substr($cleanContent, $start, $length);
        $centerOffset = $pos - $start;

        $candidates = [];

        // 1. Patrón con símbolo explícito o prefijo monetario (Prioridad Alta)
        // Ejemplo: $ 1,500.00 | $23.88 | USD 45,50 | PVP: 12.00 | PRECIO: $ 25.00
        $prefixPattern = '/(?:[\$]|USD|US\$|U\$S|PVP|P\.V\.P|PRECIO|VALOR|COSTO)\s*:?\s*([0-9]{1,3}(?:[,\.][0-9]{3})*(?:[\.,][0-9]{2})|[0-9]+(?:[\.,][0-9]{2})?)/iu';
        if (preg_match_all($prefixPattern, $window, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $m) {
                $val = $this->parsePriceValue($m[0]);
                if ($val !== null) {
                    $dist = abs($m[1] - $centerOffset);
                    $candidates[] = [
                        'val'   => $val,
                        'score' => 250 - $dist,
                    ];
                }
            }
        }

        // Ejemplo sufijo: 1,500.00 $ | 23.88 USD | 45.00 dólares
        $suffixPattern = '/([0-9]{1,3}(?:[,\.][0-9]{3})*(?:[\.,][0-9]{2})|[0-9]+(?:[\.,][0-9]{2})?)\s*(?:[\$]|USD|US\$|DOLARES|DÓLARES)/iu';
        if (preg_match_all($suffixPattern, $window, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $m) {
                $val = $this->parsePriceValue($m[0]);
                if ($val !== null) {
                    $dist = abs($m[1] - $centerOffset);
                    $candidates[] = [
                        'val'   => $val,
                        'score' => 240 - $dist,
                    ];
                }
            }
        }

        // 2. Patrón numérico con formato decimal estricto (con miles o sin miles)
        // Ejemplo: 1,500.00 | 1.500,00 | 23.88 | 23,88 | 65.00APAC (pegado a letras)
        $decimalPattern = '/(?:^|[\s\(\[\:\;\-])([0-9]{1,3}(?:[,.][0-9]{3})+(?:[.,][0-9]{2})|[0-9]{1,5}[.,][0-9]{2})(?=$|[\s\)\]\,\;\.\-]|[A-Za-z])/u';
        if (preg_match_all($decimalPattern, $window, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $m) {
                $val = $this->parsePriceValue($m[0]);
                if ($val !== null) {
                    $dist = abs($m[1] - $centerOffset);
                    // Bonus si está justo después de la descripción (patrón común: Descripción -> Precio)
                    $afterBonus = ($m[1] >= $centerOffset && ($m[1] - $centerOffset) <= 120) ? 35 : 0;
                    $candidates[] = [
                        'val'   => $val,
                        'score' => 120 - $dist + $afterBonus,
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Ordenar candidatos por mayor puntuación
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return number_format($candidates[0]['val'], 2, '.', '');
    }
}

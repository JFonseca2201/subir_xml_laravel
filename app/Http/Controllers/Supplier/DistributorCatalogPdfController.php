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
                        $snippet = $this->generateSnippet($pageText, $searchTerm);
                        $fileUrl = $catalog->file_url;
                        $pageNumber = $pageItem['page'];

                        $allMatches[] = [
                            'id'            => "cat_{$catalog->id}_p_{$pageNumber}",
                            'catalog_id'    => $catalog->id,
                            'supplier_id'   => $catalog->supplier_id,
                            'supplier_name' => $catalog->supplier_name,
                            'catalog_title' => $catalog->title ?: $catalog->file_name,
                            'file_name'     => $catalog->file_name,
                            'page_number'   => $pageNumber,
                            'snippet'       => $snippet,
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
    private function generateSnippet(string $content, string $searchTerm, int $contextChars = 140): string
    {
        $cleanContent = preg_replace('/\s+/', ' ', trim($content));
        $lowerContent = mb_strtolower($cleanContent);
        $lowerTerm = mb_strtolower(trim($searchTerm));

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
            return mb_substr($cleanContent, 0, $contextChars * 2) . '...';
        }

        $start = max(0, $pos - $contextChars);
        $length = ($pos - $start) + mb_strlen($lowerTerm) + $contextChars;

        $snippet = mb_substr($cleanContent, $start, $length);

        if ($start > 0) {
            $snippet = '...' . ltrim($snippet);
        }

        if (($start + $length) < mb_strlen($cleanContent)) {
            $snippet = rtrim($snippet) . '...';
        }

        // Escapar HTML por seguridad y resaltar palabras clave con clase catalog-highlight
        $safeSnippet = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
        $words = array_filter(explode(' ', trim($searchTerm)), fn($w) => mb_strlen(trim($w)) >= 2);
        if (!empty($words)) {
            $escapedWords = array_map(fn($w) => preg_quote(trim($w), '/'), $words);
            $pattern = '/(' . implode('|', $escapedWords) . ')/iu';
            $safeSnippet = preg_replace($pattern, '<span class="catalog-highlight">$1</span>', $safeSnippet);
        }

        return $safeSnippet;
    }
}

<?php

namespace App\Jobs;

use App\Models\Supplier\DistributorCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class ProcessCatalogPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutos para catálogos extensos
    public int $tries = 2;

    public function __construct(public int $catalogId) {}

    /**
     * Escenario 1: Extracción de texto plano indexado en archivo independiente.
     * La base de datos relacional ÚNICAMENTE almacena metadatos del catálogo.
     * Ningún texto ni fragmento se inserta en tablas relacionales.
     */
    public function handle(): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(600);

        $catalog = DistributorCatalog::find($this->catalogId);
        if (!$catalog) {
            Log::warning("ProcessCatalogPdfJob: No se encontró el catálogo con ID #{$this->catalogId}");
            return;
        }

        $catalog->update([
            'status'        => 'processing',
            'error_message' => null,
        ]);

        try {
            $fullPdfPath = Storage::disk('public')->path($catalog->file_path);

            if (!file_exists($fullPdfPath)) {
                throw new \RuntimeException("El archivo físico del catálogo no existe en: {$fullPdfPath}");
            }

            // Extracción nativa con Smalot\PdfParser\Parser
            $parser = new Parser();
            $pdf = $parser->parseFile($fullPdfPath);
            $pages = $pdf->getPages();

            $totalPages = count($pages);
            $indexedPages = [];

            foreach ($pages as $index => $page) {
                $pageNumber = $index + 1;

                try {
                    $rawText = $page->getText();
                } catch (Throwable $pe) {
                    $rawText = '';
                }

                // Limpieza de caracteres binarios, nulos o de control no imprimibles
                $cleanText = $this->cleanExtractedText($rawText);

                if (empty($cleanText)) {
                    continue; // Página sin texto legible o solo imágenes escaneadas
                }

                $indexedPages[] = [
                    'page' => $pageNumber,
                    'text' => $cleanText,
                ];
            }

            // Guardar índice estructurado en archivo independiente en disco (NO en base de datos)
            $indexRelativeDir = 'catalogs/indexes';
            $indexFilename = "catalog_{$catalog->id}_index.json";
            $indexPath = "{$indexRelativeDir}/{$indexFilename}";

            $indexData = [
                'catalog_id'    => $catalog->id,
                'supplier_id'   => $catalog->supplier_id,
                'supplier_name' => $catalog->supplier_name,
                'title'         => $catalog->title,
                'file_name'     => $catalog->file_name,
                'total_pages'   => $totalPages,
                'indexed_pages' => count($indexedPages),
                'generated_at'  => now()->toIso8601String(),
                'pages'         => $indexedPages,
            ];

            if (!Storage::disk('public')->exists($indexRelativeDir)) {
                Storage::disk('public')->makeDirectory($indexRelativeDir);
            }

            Storage::disk('public')->put(
                $indexPath,
                json_encode($indexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            // También generamos un archivo de texto plano complementario .txt para lectura directa
            $txtPath = "{$indexRelativeDir}/catalog_{$catalog->id}_text.txt";
            $fullTextContent = "CATALOGO: {$catalog->title} | PROVEEDOR: {$catalog->supplier_name}\n" .
                               "PAGINAS: {$totalPages} | FECHA: " . now()->format('Y-m-d H:i:s') . "\n\n";

            foreach ($indexedPages as $item) {
                $fullTextContent .= "=== [PAGINA {$item['page']}] ===\n{$item['text']}\n\n";
            }

            Storage::disk('public')->put($txtPath, $fullTextContent);

            // Actualizar ÚNICAMENTE los metadatos en la base de datos
            $catalog->update([
                'status'      => 'completed',
                'total_pages' => $totalPages,
                'index_path'  => $indexPath,
            ]);

            Log::info("ProcessCatalogPdfJob: Catálogo #{$catalog->id} indexado exitosamente en archivo independiente: {$indexPath}. Páginas con texto: " . count($indexedPages));
        } catch (Throwable $e) {
            Log::error("Error en ProcessCatalogPdfJob para catálogo #{$catalog->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $catalog->update([
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);
        }
    }

    /**
     * Sanitiza el texto extraído removiendo caracteres nulos, basura binaria y normalizando espacios
     */
    private function cleanExtractedText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Eliminar bytes nulos y caracteres de control no imprimibles (excepto tabs y saltos de línea)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text);

        // Si la codificación falló, convertir a UTF-8 válido
        if (!mb_check_encoding($clean, 'UTF-8')) {
            $clean = mb_convert_encoding($clean, 'UTF-8', 'auto');
        }

        // Normalizar múltiples espacios en blanco sucesivos
        $clean = preg_replace('/[ \t]+/u', ' ', $clean);

        // Limpiar líneas vacías excesivas
        $clean = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $clean);

        return trim($clean);
    }
}

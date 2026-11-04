<?php

namespace App\Models\Supplier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DistributorCatalog extends Model
{
    protected $table = 'distributor_catalogs';

    /**
     * Escenario 1: La base de datos únicamente guarda los metadatos
     * del archivo PDF subido. El texto y páginas extraídas
     * se guardan en un archivo de texto/índice independiente en storage.
     */
    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'title',
        'file_name',
        'original_filename',
        'file_path',
        'index_path',
        'file_size',
        'total_pages',
        'status',
        'error_message',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_pages' => 'integer',
    ];

    protected $appends = ['file_url', 'formatted_file_size'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function getFileUrlAttribute(): string
    {
        if (!$this->file_path) {
            return '';
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        // Endpoint dedicado de API que garantiza stream inline del PDF en Windows y Linux
        if ($this->id) {
            return url("api/distributor-catalog-pdf/{$this->id}/file");
        }

        return url('storage/' . ltrim($this->file_path, '/'));
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 KB';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}

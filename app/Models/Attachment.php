<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_path',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'url',
        'is_image',
        'is_pdf',
    ];

    /**
     * Get the owning attachable model.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * URL pública del archivo.
     */
    public function getUrlAttribute(): string
    {
        if (!$this->file_path) {
            return '';
        }

        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Determina si el archivo es una imagen.
     */
    public function getIsImageAttribute(): bool
    {
        if ($this->mime_type && str_starts_with($this->mime_type, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }

    /**
     * Determina si el archivo es un PDF.
     */
    public function getIsPdfAttribute(): bool
    {
        if ($this->mime_type === 'application/pdf') {
            return true;
        }

        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        return $extension === 'pdf';
    }
}

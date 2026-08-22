<?php

namespace App\Traits;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * Boot the trait to delete attachments and physical files when the parent model is deleted.
     */
    public static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            foreach ($model->attachments as $attachment) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
        });
    }

    /**
     * Todos los adjuntos asociados al modelo.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Solo los comprobantes de pago asociados al modelo.
     */
    public function receipts(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('type', 'receipt');
    }

    /**
     * Solo los documentos PDF principales (facturas, notas, etc.).
     */
    public function documentPdfs(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->whereIn('type', ['invoice_pdf', 'document_pdf', 'work_order_pdf']);
    }
}

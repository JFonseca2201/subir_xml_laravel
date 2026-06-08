<?php

namespace App\Mail\System;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment; // <-- Asegúrate de importar esto
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    protected $pdfRawData; // Variable para almacenar el PDF binario
    protected $pdfFileName;

    // Modificamos el constructor para que acepte opcionalmente el PDF binario y el nombre
    public function __construct(array $data, $pdfRawData = null, $pdfFileName = 'documento.pdf')
    {
        $this->data = $data;
        $this->pdfRawData = $pdfRawData;
        $this->pdfFileName = $pdfFileName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Luxury Evys - ' . ($this->data['titulo_asunto'] ?? 'Notificación de Sistema'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system.testmail',
        );
    }

    /**
     * Adjuntos del correo
     */
    public function attachments(): array
    {
        $attachments = [];

        // Si desde el controlador le enviamos el PDF en memoria, lo adjuntamos aquí
        if ($this->pdfRawData) {
            $attachments[] = Attachment::fromData(
                fn() => $this->pdfRawData,
                $this->pdfFileName
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}

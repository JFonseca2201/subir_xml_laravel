<?php

namespace App\Mail\SRI;

use App\Models\Sales\Sale;
use App\Models\Config\Sucursale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceElectronicMail extends Mailable
{
    use Queueable, SerializesModels;

    public Sale $sale;
    public Sucursale $sucursal;

    /**
     * Create a new message instance.
     */
    public function __construct(Sale $sale)
    {
        $this->sale = $sale->loadMissing(['details', 'client', 'vehicle']);
        $this->sucursal = Sucursale::find($sale->client->sucursale_id ?? 1) ?? Sucursale::first();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $empresa = $this->sucursal->trade_name ?? $this->sucursal->name ?? 'Luxury Evys';
        $numDoc = $this->sale->document_number;

        return new Envelope(
            subject: "Factura Electrónica No. {$numDoc} - {$empresa}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.electronic_invoice',
            with: [
                'sale'     => $this->sale,
                'sucursal' => $this->sucursal,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Adjuntar RIDE (PDF)
        if ($this->sale->pdf_path && Storage::exists($this->sale->pdf_path)) {
            $attachments[] = Attachment::fromData(
                fn () => Storage::get($this->sale->pdf_path),
                "Factura_{$this->sale->document_number}.pdf"
            )->withMime('application/pdf');
        }

        // Adjuntar XML firmado
        if ($this->sale->xml_path && Storage::exists($this->sale->xml_path)) {
            $attachments[] = Attachment::fromData(
                fn () => Storage::get($this->sale->xml_path),
                "Factura_{$this->sale->document_number}.xml"
            )->withMime('application/xml');
        }

        return $attachments;
    }
}

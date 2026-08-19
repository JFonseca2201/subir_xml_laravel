<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class PdfHelper
{
    /**
     * Genera un nombre de archivo PDF limpio y profesional incluyendo tipo, número, cliente y placa.
     * Ejemplo: Factura_000001586_EDWIN_ALMACHE_PBC1234.pdf
     */
    public static function formatFileName(string $type, ?string $number, $client = null, $vehicle = null): string
    {
        $parts = [];

        // 1. Tipo de documento
        $typeMap = [
            'invoice' => 'Factura',
            'sale_note' => 'Nota_Venta',
            'quote' => 'Cotizacion',
            'work_order' => 'Orden_Trabajo',
            'kardex' => 'Kardex',
        ];
        $typeLabel = $typeMap[$type] ?? Str::studly($type);
        $parts[] = $typeLabel;

        // 2. Número de documento
        if ($number) {
            $cleanNumber = preg_replace('/[^A-Za-z0-9\-_]/', '', $number);
            if (!empty($cleanNumber)) {
                $parts[] = $cleanNumber;
            }
        }

        // 3. Nombre del cliente (limpio de acentos, caracteres raros, max 30 caracteres)
        if ($client) {
            $clientName = null;
            if (is_string($client)) {
                $clientName = $client;
            } elseif (is_object($client)) {
                $clientName = $client->full_name 
                    ?? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))
                    ?? ($client->name ?? null);
            }

            if ($clientName) {
                $cleanClient = Str::upper(Str::slug($clientName, '_'));
                if (!empty($cleanClient)) {
                    $parts[] = Str::limit($cleanClient, 30, '');
                }
            }
        }

        // 4. Placa del vehículo
        if ($vehicle) {
            $plate = null;
            if (is_string($vehicle)) {
                $plate = $vehicle;
            } elseif (is_object($vehicle)) {
                $plate = $vehicle->license_plate ?? $vehicle->plate ?? null;
            }

            if ($plate) {
                $cleanPlate = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
                if (!empty($cleanPlate)) {
                    $parts[] = $cleanPlate;
                }
            }
        }

        return implode('_', $parts) . '.pdf';
    }
}

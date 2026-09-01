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

    /**
     * Genera un código de barras Code128 en Base64 PNG de alta precisión
     * a partir de una clave de acceso de 49 dígitos (o cualquier código).
     */
    public static function generateBarcodeBase64(string $code, int $height = 36): string
    {
        if (empty($code)) {
            return '';
        }

        // Patrones Code 128 (anchos de 6 elementos por carácter)
        $patterns = [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', // 0-9
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132', // 10-19
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', // 20-29
            '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', // 30-39
            '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331', // 40-49
            '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', // 50-59
            '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214', // 60-69
            '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', // 70-79
            '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', // 80-89
            '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141', // 90-99
            '114131', '311141', '411131', '211412', '211214', '211232', '2331112'                                 // 100-106
        ];

        // Usar Code C para pares de dígitos
        $clean = preg_replace('/\D/', '', $code);
        $symbols = [105]; // Start Code C
        $len = strlen($clean);

        for ($i = 0; $i < $len; $i += 2) {
            if ($i + 1 < $len) {
                $pair = (int)substr($clean, $i, 2);
                $symbols[] = $pair;
            } else {
                // Último dígito impar: cambiar a Code B (100) y codificar dígito
                $symbols[] = 100; // Code B
                $digit = (int)$clean[$i];
                $symbols[] = $digit + 16; // ASCII de '0' (48) - 32 = 16
            }
        }

        // Calcular Checksum Módulo 103
        $checksum = $symbols[0];
        for ($i = 1; $i < count($symbols); $i++) {
            $checksum += $symbols[$i] * $i;
        }
        $symbols[] = $checksum % 103;
        $symbols[] = 106; // Stop Code

        // Si GD está disponible, generar imagen PNG real para compatibilidad absoluta en DomPDF y navegadores
        if (extension_loaded('gd')) {
            $moduleWidth = 2;
            $quietZone = 10 * $moduleWidth;
            $totalModules = 0;
            foreach ($symbols as $sym) {
                $pat = $patterns[$sym] ?? '111111';
                for ($k = 0; $k < strlen($pat); $k++) {
                    $totalModules += (int)$pat[$k];
                }
            }

            $imgWidth = ($totalModules * $moduleWidth) + ($quietZone * 2);
            $imgHeight = max(40, $height * 2);

            $image = imagecreatetruecolor($imgWidth, $imgHeight);
            $bg = imagecolorallocate($image, 255, 255, 255);
            $barColor = imagecolorallocate($image, 0, 0, 0);
            imagefilledrectangle($image, 0, 0, $imgWidth, $imgHeight, $bg);

            $currentX = $quietZone;
            foreach ($symbols as $sym) {
                $pat = $patterns[$sym] ?? '111111';
                $isBar = true;
                for ($k = 0; $k < strlen($pat); $k++) {
                    $w = (int)$pat[$k] * $moduleWidth;
                    if ($isBar) {
                        imagefilledrectangle($image, $currentX, 0, $currentX + $w - 1, $imgHeight, $barColor);
                    }
                    $currentX += $w;
                    $isBar = !$isBar;
                }
            }

            ob_start();
            imagepng($image);
            $pngData = ob_get_clean();
            imagedestroy($image);

            return 'data:image/png;base64,' . base64_encode($pngData);
        }

        // Fallback SVG
        $x = 10;
        $rects = '';
        foreach ($symbols as $sym) {
            $pat = $patterns[$sym] ?? '111111';
            $isBar = true;
            for ($k = 0; $k < strlen($pat); $k++) {
                $w = (int)$pat[$k];
                if ($isBar) {
                    $rects .= "<rect x=\"{$x}\" y=\"0\" width=\"{$w}\" height=\"{$height}\" fill=\"#000\"/>";
                }
                $x += $w;
                $isBar = !$isBar;
            }
        }
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 " . ($x + 10) . " {$height}\">{$rects}</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Genera la etiqueta HTML completa del código de barras.
     */
    public static function generateBarcodeHTML(string $code, int $height = 36): string
    {
        if (empty($code)) {
            return '';
        }

        $base64 = self::generateBarcodeBase64($code, $height);
        return '<img src="' . $base64 . '" alt="Código de Barras Clave SRI" style="display:block; width:100%; max-width:275px; height:' . $height . 'px; object-fit:fill; margin:0 auto;" />';
    }
}

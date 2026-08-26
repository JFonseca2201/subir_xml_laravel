<?php

namespace App\Services\SRI;

use Exception;
use DazzaDev\SriSigner\Signer;

/**
 * Servicio de firma electrónica XAdES-BES para comprobantes del SRI Ecuador.
 * Utiliza DazzaDev\SriSigner con compatibilidad garantizada para OpenSSL 3.x y certificados de Security Data / BCE / etc.
 */
class FirmaElectronicaService
{
    /**
     * Firma el XML con el certificado .p12 usando XAdES-BES y retorna el XML firmado como string.
     */
    public function firmar(string $xmlString, string $p12Path, string $p12Password): string
    {
        if (!file_exists($p12Path)) {
            throw new Exception("Archivo de firma no encontrado: {$p12Path}");
        }

        // Asegurar que openssl en Windows XAMPP esté en el PATH
        $currentPath = getenv('PATH') ?: '';
        if (!str_contains($currentPath, 'C:\\xampp\\apache\\bin')) {
            putenv("PATH=C:\\xampp\\apache\\bin;C:\\xampp\\php;{$currentPath}");
            $_ENV['PATH'] = "C:\\xampp\\apache\\bin;C:\\xampp\\php;{$currentPath}";
            $_SERVER['PATH'] = "C:\\xampp\\apache\\bin;C:\\xampp\\php;{$currentPath}";
        }

        // Preparar certificado compatible (convirtiendo si es necesario a PKCS#12 compatible con openssl 3)
        $usableP12 = $this->prepararCertificado($p12Path, $p12Password);

        try {
            $signer = new Signer(
                certificatePath: $usableP12,
                certificatePassword: $p12Password
            );
            $signer->loadXML($xmlString);

            return $signer->sign();
        } catch (Exception $e) {
            throw new Exception('Error al firmar electrónicamente: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Prepara el certificado .p12 para ser consumido por Signer.
     * Si el .p12 usa ciphers heredados (RC2-40-CBC), lo re-exporta a formato estándar.
     */
    private function prepararCertificado(string $p12Path, string $p12Password): string
    {
        // Si el archivo ya es legible directamente por openssl_pkcs12_read, usarlo
        $content = file_get_contents($p12Path);
        $certs = [];
        if (@openssl_pkcs12_read($content, $certs, $p12Password)) {
            return $p12Path;
        }

        // Si falla por ciphers legacy (típico en OpenSSL 3), re-empaquetar con el legacy provider
        $dir = dirname($p12Path);
        $filename = 'modern_' . basename($p12Path);
        $modernP12 = $dir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($modernP12) && filemtime($modernP12) >= filemtime($p12Path)) {
            return $modernP12;
        }

        $tempPem = storage_path('app/temp_p12_' . uniqid() . '.pem');
        $cmd1 = "\"C:\\xampp\\apache\\bin\\openssl.exe\" pkcs12 -in \"{$p12Path}\" -out \"{$tempPem}\" -nodes -password pass:\"{$p12Password}\" -provider-path \"C:\\xampp\\php\\extras\\ssl\" -provider legacy -provider default 2>&1";
        shell_exec($cmd1);

        if (file_exists($tempPem) && filesize($tempPem) > 0) {
            $cmd2 = "\"C:\\xampp\\apache\\bin\\openssl.exe\" pkcs12 -export -in \"{$tempPem}\" -out \"{$modernP12}\" -password pass:\"{$p12Password}\" 2>&1";
            shell_exec($cmd2);
            @unlink($tempPem);

            if (file_exists($modernP12) && filesize($modernP12) > 0) {
                return $modernP12;
            }
        }

        if (file_exists($tempPem)) {
            @unlink($tempPem);
        }

        return $p12Path;
    }
}

<?php

namespace App\Services\SRI;

use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Firma electrónica XAdES-BES para los comprobantes del SRI Ecuador.
 *
 * Requisitos del sistema:
 *  - ext-openssl
 *  - ext-dom
 *  - Archivo .p12 (PKCS#12) válido con clave privada y certificado X.509
 *
 * Especificación SRI:
 *  https://www.sri.gob.ec/web/guest/facturacion-electronica
 */
class FirmaElectronicaService
{
    /**
     * Firma el XML con el certificado .p12 usando XAdES-BES
     * y retorna el XML firmado como string.
     *
     * @param  string $xmlString    XML sin firmar (string)
     * @param  string $p12Path      Ruta absoluta al archivo .p12
     * @param  string $p12Password  Contraseña del .p12
     * @return string               XML firmado
     *
     * @throws Exception si no se puede leer el .p12 o firmar
     */
    public function firmar(string $xmlString, string $p12Path, string $p12Password): string
    {
        if (!file_exists($p12Path)) {
            throw new Exception("Archivo de firma electrónica no encontrado: {$p12Path}");
        }

        $p12Contents = file_get_contents($p12Path);
        if ($p12Contents === false) {
            throw new Exception("No se pudo leer el archivo de firma: {$p12Path}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12Contents, $certs, $p12Password)) {
            throw new Exception('Contraseña incorrecta o archivo .p12 inválido: ' . openssl_error_string());
        }

        $privateKey  = $certs['pkey'];
        $certificate = $certs['cert'];

        // Extraer el certificado en base64 puro (sin cabeceras PEM)
        $certBase64 = $this->extraerCertBase64($certificate);

        // Calcular datos del certificado
        $x509Cert    = openssl_x509_read($certificate);
        $certDetails = openssl_x509_parse($x509Cert);
        $certIssuer  = $this->buildIssuerString($certDetails['issuer'] ?? []);
        $certSerial  = $certDetails['serialNumber'] ?? '0';

        // Parsear el XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;
        $dom->loadXML($xmlString);

        // ── 1. Calcular el digest del contenido del comprobante (SHA256) ──────────
        $comprobante = $dom->documentElement;
        $comprobante->setAttribute('Id', 'comprobante');

        $xmlC14n = $comprobante->C14N(false, false);
        $digestComprobante = base64_encode(hash('sha256', $xmlC14n, true));

        // ── 2. Construir el nodo <ds:SignedInfo> ────────────────────────
        $signedInfo = $this->buildSignedInfo($dom, $digestComprobante);

        // ── 3. Firmar el <ds:SignedInfo> con la clave privada ────────────
        $signedInfoC14n = $signedInfo->C14N(false, false);
        $signature = '';
        if (!openssl_sign($signedInfoC14n, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Error al firmar: ' . openssl_error_string());
        }
        $signatureB64 = base64_encode($signature);

        // ── 4. Ensamblar el nodo <ds:Signature> completo ─────────────────
        $sigNs   = 'http://www.w3.org/2000/09/xmldsig#';
        $xadesNs = 'http://uri.etsi.org/01903/v1.3.2#';

        $sigEl = $dom->createElementNS($sigNs, 'ds:Signature');
        $sigEl->setAttribute('Id', 'Signature');

        // Agregar el SignedInfo
        $sigEl->appendChild($signedInfo);

        // SignatureValue
        $sigValEl = $dom->createElementNS($sigNs, 'ds:SignatureValue', $signatureB64);
        $sigValEl->setAttribute('Id', 'SignatureValue');
        $sigEl->appendChild($sigValEl);

        // KeyInfo
        $keyInfoEl = $dom->createElementNS($sigNs, 'ds:KeyInfo');
        $keyInfoEl->setAttribute('Id', 'KeyInfo');
        $x509DataEl = $dom->createElementNS($sigNs, 'ds:X509Data');
        $x509CertEl = $dom->createElementNS($sigNs, 'ds:X509Certificate', $certBase64);
        $x509DataEl->appendChild($x509CertEl);
        $keyInfoEl->appendChild($x509DataEl);
        $sigEl->appendChild($keyInfoEl);

        // Object con QualifyingProperties (XAdES)
        $objectEl = $dom->createElementNS($sigNs, 'ds:Object');
        $qpEl     = $dom->createElementNS($xadesNs, 'xades:QualifyingProperties');
        $qpEl->setAttribute('Target', '#Signature');

        $spEl    = $dom->createElementNS($xadesNs, 'xades:SignedProperties');
        $spEl->setAttribute('Id', 'SignedProperties');

        $sspEl   = $dom->createElementNS($xadesNs, 'xades:SignedSignatureProperties');

        // Tiempo de firma
        $stEl = $dom->createElementNS($xadesNs, 'xades:SigningTime', now()->format('Y-m-d\TH:i:s'));
        $sspEl->appendChild($stEl);

        // Certificado firmante
        $scEl   = $dom->createElementNS($xadesNs, 'xades:SigningCertificate');
        $certEl = $dom->createElementNS($xadesNs, 'xades:Cert');

        $cdEl  = $dom->createElementNS($xadesNs, 'xades:CertDigest');
        $amEl  = $dom->createElementNS($xadesNs, 'ds:DigestMethod');
        $amEl->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $dvEl  = $dom->createElementNS($xadesNs, 'ds:DigestValue',
            base64_encode(openssl_x509_fingerprint($x509Cert, 'sha256', true))
        );
        $cdEl->appendChild($amEl);
        $cdEl->appendChild($dvEl);
        $certEl->appendChild($cdEl);

        $issEl  = $dom->createElementNS($xadesNs, 'xades:IssuerSerial');
        $x509IsEl = $dom->createElementNS($sigNs, 'ds:X509IssuerName', htmlspecialchars($certIssuer));
        $x509SrEl = $dom->createElementNS($sigNs, 'ds:X509SerialNumber', $certSerial);
        $issEl->appendChild($x509IsEl);
        $issEl->appendChild($x509SrEl);
        $certEl->appendChild($issEl);

        $scEl->appendChild($certEl);
        $sspEl->appendChild($scEl);
        $spEl->appendChild($sspEl);
        $qpEl->appendChild($spEl);
        $objectEl->appendChild($qpEl);
        $sigEl->appendChild($objectEl);

        // Agregar la firma al documento
        $dom->documentElement->appendChild($sigEl);

        return $dom->saveXML();
    }

    /**
     * Extrae el contenido base64 del certificado PEM (sin cabeceras).
     */
    private function extraerCertBase64(string $certPem): string
    {
        $certPem = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $certPem);
        $certPem = preg_replace('/-----END CERTIFICATE-----/', '', $certPem);
        return trim(str_replace(["\n", "\r"], '', $certPem));
    }

    /**
     * Construye el string del issuer en formato X.509 (CN=...,O=...,C=...).
     */
    private function buildIssuerString(array $issuer): string
    {
        $parts = [];
        foreach (['CN', 'O', 'OU', 'L', 'ST', 'C'] as $key) {
            if (!empty($issuer[$key])) {
                $parts[] = "{$key}={$issuer[$key]}";
            }
        }
        return implode(',', array_reverse($parts));
    }

    /**
     * Construye el nodo DOMElement de <ds:SignedInfo>.
     */
    private function buildSignedInfo(DOMDocument $doc, string $digestComprobante): \DOMElement
    {
        $sigNs  = 'http://www.w3.org/2000/09/xmldsig#';
        
        $si = $doc->createElementNS($sigNs, 'ds:SignedInfo');

        $cm = $doc->createElementNS($sigNs, 'ds:CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $si->appendChild($cm);

        $sm = $doc->createElementNS($sigNs, 'ds:SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $si->appendChild($sm);

        // Reference al comprobante
        $ref = $doc->createElementNS($sigNs, 'ds:Reference');
        $ref->setAttribute('URI', '#comprobante');
        $transforms = $doc->createElementNS($sigNs, 'ds:Transforms');
        $transform  = $doc->createElementNS($sigNs, 'ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform);
        $ref->appendChild($transforms);
        $dm = $doc->createElementNS($sigNs, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $dv = $doc->createElementNS($sigNs, 'ds:DigestValue', $digestComprobante);
        $ref->appendChild($dm);
        $ref->appendChild($dv);
        $si->appendChild($ref);

        return $si;
    }
}

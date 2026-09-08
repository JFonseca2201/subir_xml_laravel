<?php

namespace App\Services\SRI;

use Exception;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

/**
 * Comunicación con los Web Services SOAP del SRI Ecuador mediante cURL HTTP POST.
 * Compatible con cualquier instalación PHP (no requiere ext-soap).
 *
 * WS de Recepción:     RecepcionComprobantesOffline
 * WS de Autorización:  AutorizacionComprobantesOffline
 */
class SriWebServiceService
{
    private int $ambiente;

    public function __construct(?int $ambiente = null)
    {
        if ($ambiente !== null) {
            $this->ambiente = $ambiente;
        } else {
            $sucursal = \App\Models\Config\Sucursale::first();
            $this->ambiente = $sucursal && !empty($sucursal->ambiente)
                ? (int) $sucursal->ambiente
                : (int) env('SRI_AMBIENTE', 1);
        }
    }

    public function setAmbiente(int $ambiente): self
    {
        $this->ambiente = $ambiente;
        return $this;
    }

    /**
     * Obtiene la URL del endpoint sin ?wsdl
     */
    private function getEndpointUrl(string $url): string
    {
        return preg_replace('/\?wsdl$/i', '', trim($url));
    }

    /**
     * Envía el comprobante firmado (en base64) al WS de recepción del SRI.
     *
     * @param  string $xmlFirmado XML firmado (string plano, NO base64)
     * @return array  ['estado' => 'RECIBIDA|DEVUELTA', 'errores' => [], 'comprobante' => '...']
     *
     * @throws Exception
     */
    public function enviarComprobante(string $xmlFirmado): array
    {
        $rawWsdl = $this->ambiente === 2
            ? (env('SRI_URL_RECEPCION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl')
            : (env('SRI_URL_RECEPCION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl');

        $url = $this->getEndpointUrl($rawWsdl);
        $xmlBase64 = base64_encode($xmlFirmado);

        $soapEnvelope = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.recepcion">
   <soapenv:Header/>
   <soapenv:Body>
      <ec:validarComprobante>
         <xml>{$xmlBase64}</xml>
      </ec:validarComprobante>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        $responseXml = $this->ejecutarSoapCurl($url, $soapEnvelope, 'validarComprobante');

        return $this->parsearRespuestaRecepcionXml($responseXml);
    }

    /**
     * Consulta la autorización de un comprobante por su clave de acceso.
     *
     * @param  string $claveAcceso Clave de 49 dígitos
     * @return array  ['estado' => 'AUTORIZADA|EN_PROCESO|NO_AUTORIZADA', 'fechaAutorizacion' => '...', 'errores' => [], 'mensajes' => []]
     *
     * @throws Exception
     */
    public function autorizarComprobante(string $claveAcceso): array
    {
        $rawWsdl = $this->ambiente === 2
            ? (env('SRI_URL_AUTORIZACION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl')
            : (env('SRI_URL_AUTORIZACION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl');

        $url = $this->getEndpointUrl($rawWsdl);

        $soapEnvelope = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.autorizacion">
   <soapenv:Header/>
   <soapenv:Body>
      <ec:autorizacionComprobante>
         <claveAccesoComprobante>{$claveAcceso}</claveAccesoComprobante>
      </ec:autorizacionComprobante>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        $responseXml = $this->ejecutarSoapCurl($url, $soapEnvelope, 'autorizacionComprobante');

        return $this->parsearRespuestaAutorizacionXml($responseXml);
    }

    /**
     * Ejecuta una petición SOAP directa usando cURL
     *
     * @throws Exception
     */
    private function ejecutarSoapCurl(string $url, string $soapXml, string $action): string
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastError = '';
        $response = false;
        $httpCode = 0;

        while ($attempt < $maxRetries) {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $soapXml);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Content-Length: ' . strlen($soapXml),
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 35);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $lastError = curl_error($ch);
            curl_close($ch);

            if (!$lastError && ($httpCode === 200 || !empty($response))) {
                return $response ?: '';
            }

            if ($attempt < $maxRetries) {
                sleep(2);
            }
        }

        if ($lastError) {
            Log::error("[SRI cURL Error] Acción {$action} en {$url}: {$lastError} tras {$maxRetries} intentos");
            throw new Exception("Error de conexión cURL al SRI ({$action}): {$lastError}");
        }

        if ($httpCode !== 200 && empty($response)) {
            Log::error("[SRI HTTP Error] HTTP {$httpCode} en {$url}");
            throw new Exception("El servidor del SRI respondió con código HTTP {$httpCode}");
        }

        return $response ?: '';
    }

    /**
     * Parsea el XML recibido en la validación/recepción
     */
    private function parsearRespuestaRecepcionXml(string $rawXml): array
    {
        $result = ['estado' => 'DEVUELTA', 'errores' => []];

        if (empty($rawXml)) {
            $result['errores'][] = 'Respuesta vacía del servidor SRI';
            return $result;
        }

        try {
            // Limpiar namespaces para facilitar lectura con SimpleXML
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $rawXml);
            $xml = new SimpleXMLElement($cleanXml);

            $respuesta = $xml->xpath('//RespuestaRecepcionComprobante');
            if (empty($respuesta)) {
                $respuesta = $xml->xpath('//respuestaRecepcionComprobante');
            }

            if (empty($respuesta)) {
                $result['errores'][] = 'Estructura no reconocida en respuesta del SRI';
                return $result;
            }

            $respObj = $respuesta[0];
            $estado = (string) ($respObj->estado ?? 'DEVUELTA');
            $result['estado'] = strtoupper(trim($estado));

            // Extraer mensajes de error
            $mensajes = $respObj->xpath('.//mensaje');
            foreach ($mensajes as $msg) {
                $tipo = (string) ($msg->tipo ?? 'ERROR');
                $texto = (string) ($msg->mensaje ?? '');
                $info = (string) ($msg->informacionAdicional ?? '');
                $full = trim($tipo . ': ' . $texto . ($info ? ' — ' . $info : ''));
                if ($full) {
                    $result['errores'][] = $full;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("[SRI Recepción Parse Error]: " . $e->getMessage() . " | Raw: " . substr($rawXml, 0, 500));
            $result['errores'][] = 'Error al procesar respuesta del SRI: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Parsea el XML recibido en la autorización
     */
    private function parsearRespuestaAutorizacionXml(string $rawXml): array
    {
        $result = [
            'estado'             => 'NO_AUTORIZADA',
            'fechaAutorizacion'  => null,
            'numeroAutorizacion' => null,
            'errores'            => [],
            'mensajes'           => [],
        ];

        if (empty($rawXml)) {
            $result['errores'][] = 'Respuesta vacía del servidor de autorización SRI';
            return $result;
        }

        try {
            // Limpiar namespaces para facilitar lectura con SimpleXML
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $rawXml);
            $xml = new SimpleXMLElement($cleanXml);

            $autorizaciones = $xml->xpath('//autorizacion');
            if (empty($autorizaciones)) {
                // Verificar si está en proceso o sin comprobantes
                $numeroComprobantes = $xml->xpath('//numeroComprobantes');
                if (!empty($numeroComprobantes) && (int)$numeroComprobantes[0] === 0) {
                    $result['estado'] = 'EN_PROCESO';
                    return $result;
                }
                $result['estado'] = 'EN_PROCESO';
                return $result;
            }

            // Tomar la primera autorización
            $auth = $autorizaciones[0];
            $estado = (string) ($auth->estado ?? 'NO_AUTORIZADA');
            $result['estado'] = strtoupper(trim($estado));
            $result['fechaAutorizacion'] = !empty($auth->fechaAutorizacion) ? (string)$auth->fechaAutorizacion : null;
            $result['numeroAutorizacion'] = !empty($auth->numeroAutorizacion) ? (string)$auth->numeroAutorizacion : null;

            // Extraer mensajes
            $mensajes = $auth->xpath('.//mensaje');
            foreach ($mensajes as $msg) {
                $tipo = (string) ($msg->tipo ?? 'INFO');
                $texto = (string) ($msg->mensaje ?? '');
                $info = (string) ($msg->informacionAdicional ?? '');
                $full = trim($tipo . ': ' . $texto . ($info ? ' — ' . $info : ''));
                if ($full) {
                    $result['mensajes'][] = $full;
                    if (strtoupper($tipo) === 'ERROR') {
                        $result['errores'][] = $full;
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("[SRI Autorización Parse Error]: " . $e->getMessage() . " | Raw: " . substr($rawXml, 0, 500));
            $result['errores'][] = 'Error al procesar autorización del SRI: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Verifica la disponibilidad y conectividad con los Web Services del SRI.
     * Realiza un sondeo HTTP cURL de los endpoints WSDL de Recepción y Autorización.
     *
     * @param int|null $ambiente 1 = Pruebas, 2 = Producción (opcional, por defecto el configurado)
     * @return array
     */
    public function verificarConexion(?int $ambiente = null): array
    {
        $targetAmbiente = $ambiente !== null ? $ambiente : $this->ambiente;

        $urlRecepcion = $targetAmbiente === 2
            ? (env('SRI_URL_RECEPCION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl')
            : (env('SRI_URL_RECEPCION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl');

        $urlAutorizacion = $targetAmbiente === 2
            ? (env('SRI_URL_AUTORIZACION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl')
            : (env('SRI_URL_AUTORIZACION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl');

        $recepcion = $this->probarEndpoint($urlRecepcion, 'Recepción de Comprobantes');
        $autorizacion = $this->probarEndpoint($urlAutorizacion, 'Autorización de Comprobantes');

        $bothOnline = $recepcion['online'] && $autorizacion['online'];
        $noneOnline = !$recepcion['online'] && !$autorizacion['online'];

        if ($bothOnline) {
            $hasHighLatency = ($recepcion['latency_ms'] > 3500 || $autorizacion['latency_ms'] > 3500);
            $status = $hasHighLatency ? 'LENTO' : 'OPERATIVO';
            $message = $hasHighLatency
                ? 'Los servicios del SRI responden pero presentan alta latencia. Los comprobantes podrían demorar en procesarse.'
                : 'Los servicios del SRI se encuentran en línea y funcionando con normalidad.';
        } elseif ($noneOnline) {
            $status = 'FUERA_DE_SERVICIO';
            $message = 'Los servidores del SRI se encuentran fuera de servicio o inaccesibles. Los comprobantes enviados saldrán con error o rechazados hasta que el SRI restablezca el servicio.';
        } else {
            $status = 'PARCIAL';
            $message = 'Servicio del SRI parcialmente disponible: ' . 
                (!$recepcion['online'] ? 'El servicio de Recepción no responde. ' : '') .
                (!$autorizacion['online'] ? 'El servicio de Autorización no responde.' : '');
        }

        return [
            'online'        => $bothOnline,
            'status'        => $status, // OPERATIVO | LENTO | PARCIAL | FUERA_DE_SERVICIO
            'message'       => $message,
            'ambiente'      => $targetAmbiente,
            'ambiente_name' => $targetAmbiente === 2 ? 'PRODUCCIÓN' : 'PRUEBAS',
            'timestamp'     => now()->toIso8601String(),
            'services'      => [
                'recepcion'    => $recepcion,
                'autorizacion' => $autorizacion,
            ],
        ];
    }

    /**
     * Sonda un endpoint cURL específico del SRI y mide latencia y código de respuesta.
     */
    private function probarEndpoint(string $url, string $nombreServicio): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FacturacionSRI/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/xml, application/xml, text/html, */*',
        ]);

        $response = curl_exec($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $latencyMs = (int) round($totalTime * 1000);

        // Determinación de estado
        if ($curlErrno !== 0 || $httpCode === 0) {
            return [
                'name'         => $nombreServicio,
                'url'          => $url,
                'online'       => false,
                'http_code'    => $httpCode,
                'latency_ms'   => $latencyMs,
                'status'       => 'CAIDO',
                'status_label' => 'Sin Conexión',
                'message'      => 'No se pudo conectar con el servidor del SRI (Tiempo de espera agotado o conexión rechazada).',
                'error'        => $curlError ?: "Error de red cURL código {$curlErrno}",
            ];
        }

        if ($httpCode >= 500) {
            return [
                'name'         => $nombreServicio,
                'url'          => $url,
                'online'       => false,
                'http_code'    => $httpCode,
                'latency_ms'   => $latencyMs,
                'status'       => 'ERROR_SERVIDOR',
                'status_label' => 'Fallo Servidor SRI',
                'message'      => "El servidor del SRI retornó error HTTP {$httpCode}.",
                'error'        => "Respuesta del SRI: HTTP {$httpCode}",
            ];
        }

        // Si responde HTTP 200 (o 405/204 con contenido)
        $isOk = ($httpCode === 200 || !empty($response));
        $isSlow = ($latencyMs > 3500);

        return [
            'name'         => $nombreServicio,
            'url'          => $url,
            'online'       => $isOk,
            'http_code'    => $httpCode,
            'latency_ms'   => $latencyMs,
            'status'       => $isSlow ? 'LENTO' : ($isOk ? 'OPERATIVO' : 'DEGRADADO'),
            'status_label' => $isSlow ? 'Respuesta Lenta' : ($isOk ? 'Operativo' : 'Inestable'),
            'message'      => $isSlow
                ? "Operativo con respuesta lenta ({$latencyMs} ms)."
                : "Operativo y respondiendo correctamente ({$latencyMs} ms).",
            'error'        => null,
        ];
    }
}


<?php

namespace App\Services\SRI;

use Exception;
use SoapClient;
use SoapFault;

/**
 * Comunicación con los Web Services SOAP del SRI Ecuador.
 *
 * WS de Recepción:     RecepcionComprobantesOffline
 * WS de Autorización:  AutorizacionComprobantesOffline
 */
class SriWebServiceService
{
    private int $ambiente;

    public function __construct()
    {
        $this->ambiente = (int) env('SRI_AMBIENTE', 1);
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
        $wsdl = $this->ambiente === 2
            ? (env('SRI_URL_RECEPCION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl')
            : (env('SRI_URL_RECEPCION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl');

        $xmlBase64 = base64_encode($xmlFirmado);

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $client = new SoapClient($wsdl, [
                'connection_timeout' => 30,
                'exceptions'         => true,
                'trace'              => true,
                'stream_context'     => $context,
            ]);

            $response = $client->validarComprobante([
                'xml' => $xmlBase64,
            ]);

            return $this->parsearRespuestaRecepcion($response);

        } catch (SoapFault $e) {
            throw new Exception('Error SOAP en recepción SRI: ' . $e->getMessage(), 0, $e);
        }
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
        $wsdl = $this->ambiente === 2
            ? (env('SRI_URL_AUTORIZACION_PRODUCCION') ?: 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl')
            : (env('SRI_URL_AUTORIZACION_PRUEBAS') ?: 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl');

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $client = new SoapClient($wsdl, [
                'connection_timeout' => 30,
                'exceptions'         => true,
                'trace'              => true,
                'stream_context'     => $context,
            ]);

            $response = $client->autorizacionComprobante([
                'claveAccesoComprobante' => $claveAcceso,
            ]);

            return $this->parsearRespuestaAutorizacion($response);

        } catch (SoapFault $e) {
            throw new Exception('Error SOAP en autorización SRI: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parsea la respuesta del WS de recepción.
     */
    private function parsearRespuestaRecepcion(mixed $response): array
    {
        $result = ['estado' => 'DEVUELTA', 'errores' => []];

        $respuesta = $response->RespuestaRecepcionComprobante
            ?? $response->respuestaRecepcionComprobante
            ?? null;

        if (!$respuesta) {
            $result['errores'][] = 'Respuesta inválida del SRI';
            return $result;
        }

        $estado = is_object($respuesta)
            ? ($respuesta->estado ?? 'DEVUELTA')
            : 'DEVUELTA';

        $result['estado'] = strtoupper($estado);

        // Capturar comprobantes devueltos con errores
        if (!empty($respuesta->comprobantes->comprobante->mensajes->mensaje)) {
            $mensajes = $respuesta->comprobantes->comprobante->mensajes->mensaje;
            if (!is_array($mensajes)) {
                $mensajes = [$mensajes];
            }
            foreach ($mensajes as $msg) {
                $result['errores'][] = ($msg->tipo ?? 'ERROR') . ': ' . ($msg->mensaje ?? '') .
                    (isset($msg->informacionAdicional) ? ' — ' . $msg->informacionAdicional : '');
            }
        }

        return $result;
    }

    /**
     * Parsea la respuesta del WS de autorización.
     */
    private function parsearRespuestaAutorizacion(mixed $response): array
    {
        $result = [
            'estado'             => 'NO_AUTORIZADA',
            'fechaAutorizacion'  => null,
            'numeroAutorizacion' => null,
            'errores'            => [],
            'mensajes'           => [],
        ];

        $respuesta = $response->RespuestaAutorizacionComprobante
            ?? $response->respuestaAutorizacionComprobante
            ?? null;

        if (!$respuesta) {
            $result['errores'][] = 'Respuesta inválida del SRI en autorización';
            return $result;
        }

        // El SRI devuelve un objeto o array de autorizaciones
        $autorizaciones = $respuesta->autorizaciones->autorizacion ?? null;
        if (!$autorizaciones) {
            $result['estado']    = 'EN_PROCESO';
            return $result;
        }

        if (!is_array($autorizaciones)) {
            $autorizaciones = [$autorizaciones];
        }

        // Tomamos la primera autorización (una clave = un comprobante)
        $auth = $autorizaciones[0];

        $result['estado']             = strtoupper($auth->estado ?? 'NO_AUTORIZADA');
        $result['fechaAutorizacion']  = $auth->fechaAutorizacion ?? null;
        $result['numeroAutorizacion'] = $auth->numeroAutorizacion ?? null;

        // Mensajes de error/advertencia
        if (!empty($auth->mensajes->mensaje)) {
            $mensajes = $auth->mensajes->mensaje;
            if (!is_array($mensajes)) {
                $mensajes = [$mensajes];
            }
            foreach ($mensajes as $msg) {
                $text = ($msg->tipo ?? 'INFO') . ': ' . ($msg->mensaje ?? '');
                if (!empty($msg->informacionAdicional)) {
                    $text .= ' — ' . $msg->informacionAdicional;
                }
                $result['mensajes'][] = $text;
                if (($msg->tipo ?? '') === 'ERROR') {
                    $result['errores'][] = $text;
                }
            }
        }

        return $result;
    }
}

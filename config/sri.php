<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SRI Ecuador Configuration
    |--------------------------------------------------------------------------
    |
    | Ambiente: 1 = Pruebas, 2 = Producción
    | Autoprocess: true = procesar automáticamente factura electrónica tras venta
    |
    */
    'ambiente' => (int) env('SRI_AMBIENTE', 1),
    'autoprocess' => filter_var(env('SRI_AUTOPROCESS', true), FILTER_VALIDATE_BOOLEAN),

    'urls' => [
        'recepcion' => [
            1 => env('SRI_URL_RECEPCION_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl'),
            2 => env('SRI_URL_RECEPCION_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl'),
        ],
        'autorizacion' => [
            1 => env('SRI_URL_AUTORIZACION_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl'),
            2 => env('SRI_URL_AUTORIZACION_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl'),
        ],
    ],

    'openssl_path' => env('OPENSSL_PATH', null),
    'printer_name' => env('PRINTER_NAME', 'L5290 Series(Network)'),
];


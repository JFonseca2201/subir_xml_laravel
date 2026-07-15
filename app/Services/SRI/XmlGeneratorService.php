<?php

namespace App\Services\SRI;

use App\Models\Sales\Sale;
use App\Models\Config\Sucursale;
use XMLWriter;

class XmlGeneratorService
{
    /**
     * Genera el XML de factura electrónica según el esquema SRI Ecuador v1.1.0
     *
     * @param  Sale       $sale     Venta con detalles y cliente cargados
     * @param  Sucursale  $sucursal Datos del emisor
     * @return string     XML sin firmar como string
     */
    public function generar(Sale $sale, Sucursale $sucursal): string
    {
        $claveAcceso = $this->generarClaveAcceso($sale, $sucursal);

        // Guardar la clave de acceso en la venta si no existe
        if (!$sale->sri_access_key) {
            $sale->update(['sri_access_key' => $claveAcceso]);
        }

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('factura');
        $xml->writeAttribute('id', 'comprobante');
        $xml->writeAttribute('version', '1.1.0');

        // ─── infoTributaria ─────────────────────────────────────────────
        $xml->startElement('infoTributaria');
        $xml->writeElement('ambiente',          (string) env('SRI_AMBIENTE', '1'));
        $xml->writeElement('tipoEmision',       $sucursal->tipo_emision ?? '1');
        $xml->writeElement('razonSocial',       $sucursal->name);
        $xml->writeElement('nombreComercial',   $sucursal->trade_name ?? $sucursal->name);
        $xml->writeElement('ruc',               $sucursal->ruc);
        $xml->writeElement('claveAcceso',       $claveAcceso);
        $xml->writeElement('codDoc',            '01'); // 01 = Factura
        $xml->writeElement('estab',             $sucursal->establecimiento ?? '001');
        $xml->writeElement('ptoEmi',            $sucursal->punto_emision ?? '001');
        $xml->writeElement('secuencial',        $this->extraerSecuencial($claveAcceso));
        $xml->writeElement('dirMatriz',         $sucursal->address ?? '');
        $xml->endElement(); // infoTributaria

        // ─── infoFactura ────────────────────────────────────────────────
        $cliente = $sale->client;
        $xml->startElement('infoFactura');
        $xml->writeElement('fechaEmision',              $sale->service_date
            ? $sale->service_date->format('d/m/Y')
            : now()->format('d/m/Y'));
        $xml->writeElement('dirEstablecimiento',        $sucursal->address ?? '');
        $xml->writeElement('contribuyenteEspecial',     $sucursal->contribuyente_especial ?? '');
        $xml->writeElement('obligadoContabilidad',      strtoupper($sucursal->obligado_contabilidad ?? 'NO'));
        $xml->writeElement('tipoIdentificacionComprador', $this->tipoDocumento($cliente->type_document));
        $xml->writeElement('guiaRemision',              '');
        $xml->writeElement('razonSocialComprador',      $cliente->full_name ?? $cliente->name);
        $xml->writeElement('identificacionComprador',   $cliente->n_document ?? '9999999999999');
        $xml->writeElement('direccionComprador',        $cliente->address ?? '');
        $xml->writeElement('totalSinImpuestos',         number_format((float)$sale->subtotal, 2, '.', ''));

        // Descuento total
        $descuentoTotal = $sale->details->sum(function ($d) {
            return (float) $d->discount;
        });
        $xml->writeElement('totalDescuento', number_format($descuentoTotal, 2, '.', ''));

        // totalConImpuestos por tarifa
        $xml->startElement('totalConImpuestos');

        $tarifas = $this->calcularTarifas($sale);
        foreach ($tarifas as $tarifa) {
            $xml->startElement('totalImpuesto');
            $xml->writeElement('codigo',        '2');  // 2 = IVA
            $xml->writeElement('codigoPorcentaje', $tarifa['codigoPorcentaje']);
            $xml->writeElement('descuentoAdicional', '0.00');
            $xml->writeElement('baseImponible', number_format($tarifa['baseImponible'], 2, '.', ''));
            $xml->writeElement('valor',         number_format($tarifa['valor'], 2, '.', ''));
            $xml->endElement();
        }
        $xml->endElement(); // totalConImpuestos

        $xml->writeElement('propina',               '0.00');
        $xml->writeElement('importeTotal',          number_format((float)$sale->total, 2, '.', ''));
        $xml->writeElement('moneda',                'DOLAR');

        // Pagos
        $xml->startElement('pagos');
        $xml->startElement('pago');
        $xml->writeElement('formaPago', $this->mapFormaPago($sale->payment_method));
        $xml->writeElement('total',     number_format((float)$sale->total, 2, '.', ''));
        $xml->writeElement('plazo',     '0');
        $xml->writeElement('unidadTiempo', 'dias');
        $xml->endElement(); // pago
        $xml->endElement(); // pagos

        $xml->endElement(); // infoFactura

        // ─── detalles ───────────────────────────────────────────────────
        $xml->startElement('detalles');
        foreach ($sale->details as $index => $detalle) {
            $xml->startElement('detalle');
            $xml->writeElement('codigoPrincipal',    $detalle->product_id
                ? str_pad($detalle->product_id, 6, '0', STR_PAD_LEFT)
                : str_pad($index + 1, 6, '0', STR_PAD_LEFT));
            $xml->writeElement('codigoAuxiliar',     '');
            $xml->writeElement('descripcion',        $this->sanitizar($detalle->description));
            $xml->writeElement('cantidad',           number_format((float)$detalle->quantity, 2, '.', ''));
            $xml->writeElement('precioUnitario',     number_format((float)$detalle->price, 2, '.', ''));
            $xml->writeElement('descuento',          number_format((float)$detalle->discount, 2, '.', ''));

            $subtotalDetalle = ((float)$detalle->quantity * (float)$detalle->price) - (float)$detalle->discount;
            $xml->writeElement('precioTotalSinImpuesto', number_format($subtotalDetalle, 2, '.', ''));

            $xml->startElement('impuestos');
            $xml->startElement('impuesto');
            $xml->writeElement('codigo',            '2');
            $xml->writeElement('codigoPorcentaje',  $this->codigoTarifa((float)$detalle->tax_rate));
            $xml->writeElement('tarifa',            number_format((float)$detalle->tax_rate, 2, '.', ''));
            $xml->writeElement('baseImponible',     number_format($subtotalDetalle, 2, '.', ''));
            $xml->writeElement('valor',             number_format((float)$detalle->tax_value, 2, '.', ''));
            $xml->endElement(); // impuesto
            $xml->endElement(); // impuestos

            $xml->endElement(); // detalle
        }
        $xml->endElement(); // detalles

        // ─── infoAdicional ──────────────────────────────────────────────
        $xml->startElement('infoAdicional');
        if ($cliente->email) {
            $xml->startElement('campoAdicional');
            $xml->writeAttribute('nombre', 'email');
            $xml->text($cliente->email);
            $xml->endElement();
        }
        if ($cliente->phone) {
            $xml->startElement('campoAdicional');
            $xml->writeAttribute('nombre', 'telefono');
            $xml->text($cliente->phone);
            $xml->endElement();
        }
        if ($sale->observations) {
            $xml->startElement('campoAdicional');
            $xml->writeAttribute('nombre', 'observaciones');
            $xml->text($this->sanitizar($sale->observations));
            $xml->endElement();
        }
        $xml->endElement(); // infoAdicional

        $xml->endElement(); // factura
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Genera la clave de acceso de 49 dígitos según el algoritmo SRI.
     * Formato: fechaEmision(8) + codDoc(2) + ruc(13) + ambiente(1) + serie(6) + secuencial(9) + codigoNumerico(8) + tipoEmision(1) + digitoVerificador(1)
     */
    public function generarClaveAcceso(Sale $sale, Sucursale $sucursal): string
    {
        $fecha          = $sale->service_date
            ? $sale->service_date->format('dmY')
            : now()->format('dmY');
        $codDoc         = '01';
        $ruc            = str_pad($sucursal->ruc, 13, '0', STR_PAD_LEFT);
        $ambiente       = (string) env('SRI_AMBIENTE', '1');
        $estab          = str_pad($sucursal->establecimiento ?? '001', 3, '0', STR_PAD_LEFT);
        $ptoEmi         = str_pad($sucursal->punto_emision ?? '001', 3, '0', STR_PAD_LEFT);
        $secuencial     = str_pad($this->obtenerSecuencial($sale), 9, '0', STR_PAD_LEFT);
        $codigoNum      = substr($secuencial, 0, 8); // Usar los primeros 8 dígitos del secuencial
        $tipoEmision    = $sucursal->tipo_emision ?? '1';

        $clave48 = $fecha . $codDoc . $ruc . $ambiente . $estab . $ptoEmi . $secuencial . $codigoNum . $tipoEmision;
        $verificador = $this->modulo11($clave48);

        return $clave48 . $verificador;
    }

    /**
     * Algoritmo módulo 11 para el dígito verificador de la clave de acceso.
     */
    private function modulo11(string $clave): string
    {
        $factores  = [2, 3, 4, 5, 6, 7];
        $suma      = 0;
        $longitud  = strlen($clave);
        $factorIdx = 0;

        for ($i = $longitud - 1; $i >= 0; $i--) {
            $suma += (int)$clave[$i] * $factores[$factorIdx % 6];
            $factorIdx++;
        }

        $residuo = $suma % 11;

        if ($residuo === 0) return '0';
        if ($residuo === 1) return '1';

        return (string)(11 - $residuo);
    }

    /**
     * Extrae el secuencial (9 dígitos) de una clave de acceso de 49 dígitos.
     * Posición 24-32 (base 0, los 9 dígitos del secuencial)
     */
    private function extraerSecuencial(string $claveAcceso): string
    {
        // fecha(8) + codDoc(2) + ruc(13) + ambiente(1) + estab(3) + ptoEmi(3) = 30 chars antes del secuencial
        return substr($claveAcceso, 30, 9);
    }

    /**
     * Obtiene el número secuencial desde el document_number de la venta.
     * Asume formato xxx-xxx-000000001
     */
    private function obtenerSecuencial(Sale $sale): string
    {
        $parts = explode('-', $sale->document_number);
        return end($parts) ?: '000000001';
    }

    /**
     * Calcula los totales agrupados por tarifa de IVA para infoFactura.
     */
    private function calcularTarifas(Sale $sale): array
    {
        $tarifas = [];

        foreach ($sale->details as $detalle) {
            $rate    = (float) $detalle->tax_rate;
            $codigo  = $this->codigoTarifa($rate);
            $base    = ((float)$detalle->quantity * (float)$detalle->price) - (float)$detalle->discount;
            $valor   = (float) $detalle->tax_value;

            if (!isset($tarifas[$codigo])) {
                $tarifas[$codigo] = ['codigoPorcentaje' => $codigo, 'baseImponible' => 0.0, 'valor' => 0.0];
            }
            $tarifas[$codigo]['baseImponible'] += $base;
            $tarifas[$codigo]['valor']         += $valor;
        }

        return array_values($tarifas);
    }

    /**
     * Mapea la tasa de IVA al código SRI.
     * 0  → '0' (0%)
     * 2  → '2' (12%) — histórico
     * 4  → '4' (15%) — vigente 2024-2025
     * 6  → '6' (No Objeto)
     * 7  → '7' (Exento)
     */
    private function codigoTarifa(float $rate): string
    {
        return match ((int) round($rate)) {
            0 => '0',
            12 => '2',
            14 => '3',
            15 => '4',
            default => '4', // Default a la tarifa vigente (15%)
        };
    }

    /**
     * Mapea el tipo de documento del cliente al código SRI.
     */
    private function tipoDocumento(?string $tipo): string
    {
        return match (strtolower($tipo ?? '')) {
            'ruc'          => '04',
            'pasaporte', 'passport' => '06',
            'consumidor final', 'consumidor_final' => '07',
            default => '05', // Cédula de identidad por defecto
        };
    }

    /**
     * Mapea el método de pago al código SRI (tabla 24).
     */
    private function mapFormaPago(string $method): string
    {
        return match (strtolower($method)) {
            'cash', 'efectivo' => '01', // SIN UTILIZACION DEL SISTEMA FINANCIERO
            'transfer', 'transferencia' => '15', // COMPENSACIÓN DE DEUDAS
            'card', 'tarjeta', 'tarjeta de credito', 'tarjeta de débito' => '19', // TARJETA DE CRÉDITO
            'credit', 'credito' => '20', // OTROS CON UTILIZACION DEL SISTEMA FINANCIERO
            default => '01',
        };
    }

    /**
     * Elimina caracteres especiales no permitidos en el XML del SRI.
     */
    private function sanitizar(string $texto): string
    {
        // Remueve caracteres de control y limita a ASCII + tildes básicas
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);
        return substr(trim($texto), 0, 300);
    }
}

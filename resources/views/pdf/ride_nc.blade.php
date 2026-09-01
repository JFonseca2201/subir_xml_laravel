<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>RIDE - Nota de Crédito {{ $creditNote->document_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #334155;
            background: #ffffff;
            line-height: 1.25;
            padding: 0;
            margin: 10px;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .card-panel {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 10px;
            background: #ffffff;
            box-sizing: border-box;
        }

        .card-panel-shaded {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            background: #f8fafc;
            box-sizing: border-box;
        }

        .company-title {
            font-size: 10px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .company-detail {
            font-size: 8px;
            color: #475569;
            margin-bottom: 2px;
        }

        .doc-ruc {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .doc-type {
            font-size: 13px;
            font-weight: 800;
            color: #dc2626;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .doc-num {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
            font-family: monospace;
        }

        .info-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .info-val {
            font-size: 8px;
            font-weight: 600;
            color: #1e293b;
            font-family: monospace;
            word-break: break-all;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        .items-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .items-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            font-size: 8px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 3px 6px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
        }

        .totals-table .lbl {
            font-weight: 700;
            background: #f8fafc;
            width: 60%;
        }

        .totals-table .val {
            text-align: right;
            font-family: monospace;
            font-weight: 600;
        }

        .total-highlight {
            background: #fee2e2 !important;
            color: #991b1b;
            font-size: 9px !important;
            font-weight: 800 !important;
        }

        .barcode-box {
            text-align: center;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- ── HEADER ── -->
        <table class="layout-table">
            <tr>
                <!-- Columna Izquierda: Emisor -->
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <div class="card-panel">
                        <div class="company-title">{{ $sucursal->name }}</div>
                        <div class="company-detail"><strong>Nombre Comercial:</strong> {{ $sucursal->trade_name ?? $sucursal->name }}</div>
                        <div class="company-detail"><strong>Dirección Matriz:</strong> {{ $sucursal->address }}</div>
                        <div class="company-detail"><strong>Obligado a Llevar Contabilidad:</strong> {{ strtoupper($sucursal->obligado_contabilidad ?? 'NO') }}</div>
                        @if(!empty($sucursal->contribuyente_especial))
                            <div class="company-detail"><strong>Contribuyente Especial Nro:</strong> {{ $sucursal->contribuyente_especial }}</div>
                        @endif
                    </div>
                </td>

                <!-- Columna Derecha: Clave y SRI -->
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <div class="card-panel">
                        <div class="doc-ruc">R.U.C.: {{ $sucursal->ruc }}</div>
                        <div class="doc-type">NOTA DE CRÉDITO</div>
                        <div class="doc-num">No. {{ $sucursal->establecimiento ?? '001' }}-{{ $sucursal->punto_emision ?? '001' }}-{{ str_pad(preg_replace('/\D/', '', $creditNote->document_number), 9, '0', STR_PAD_LEFT) }}</div>

                        <div class="company-detail">
                            <span class="info-label">NÚMERO DE AUTORIZACIÓN:</span><br>
                            <span class="info-val">{{ $autorizacion['numeroAutorizacion'] ?? $creditNote->sri_access_key }}</span>
                        </div>

                        <div class="company-detail" style="margin-top: 3px;">
                            <span class="info-label">FECHA Y HORA DE AUTORIZACIÓN:</span><br>
                            <span class="info-val">{{ $autorizacion['fechaAutorizacion'] ?? ($creditNote->sri_authorization_date ? $creditNote->sri_authorization_date->format('d/m/Y H:i:s') : 'EN PROCESO') }}</span>
                        </div>

                        <div class="company-detail" style="margin-top: 3px;">
                            <strong>AMBIENTE:</strong> {{ ($sucursal->ambiente ?? '1') == '2' ? 'PRODUCCIÓN' : 'PRUEBAS' }}
                            &nbsp;|&nbsp;
                            <strong>EMISIÓN:</strong> NORMAL
                        </div>

                        <div class="barcode-box">
                            <span class="info-label">CLAVE DE ACCESO</span><br>
                            @if (!empty($creditNote->sri_access_key))
                            <div style="padding: 2px 0;">
                                {!! \App\Helpers\PdfHelper::generateBarcodeHTML($creditNote->sri_access_key, 28) !!}
                            </div>
                            <span class="info-val" style="font-size: 7.5px;">{{ $creditNote->sri_access_key }}</span>
                            @else
                            <span class="info-val" style="font-size: 7.5px;">SIN CLAVE DE ACCESO</span>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- ── DATOS DEL COMPRADOR Y DOCUMENTO MODIFICADO ── -->
        <table class="layout-table">
            <tr>
                <td style="width: 100%;">
                    <div class="card-panel-shaded">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: 60%; padding: 2px;">
                                    <strong>Razón Social / Nombres:</strong> {{ $creditNote->sale->client->full_name ?? $creditNote->sale->client->name }}
                                </td>
                                <td style="width: 40%; padding: 2px;">
                                    <strong>Identificación:</strong> {{ $creditNote->sale->client->n_document }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px;">
                                    <strong>Fecha Emisión:</strong> {{ $creditNote->created_at ? $creditNote->created_at->format('d/m/Y') : now()->format('d/m/Y') }}
                                </td>
                                <td style="padding: 2px;">
                                    <strong>Comprobante que Modifica:</strong> FACTURA {{ $sucursal->establecimiento ?? '001' }}-{{ $sucursal->punto_emision ?? '001' }}-{{ str_pad(preg_replace('/\D/', '', $creditNote->sale->document_number), 9, '0', STR_PAD_LEFT) }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px;">
                                    <strong>Fecha Emisión (Comprobante Modificado):</strong> {{ $creditNote->sale->service_date ? $creditNote->sale->service_date->format('d/m/Y') : ($creditNote->sale->created_at ? $creditNote->sale->created_at->format('d/m/Y') : now()->format('d/m/Y')) }}
                                </td>
                                <td style="padding: 2px;">
                                    <strong>Razón de Modificación:</strong> <span style="color: #b91c1c; font-weight: 700;">{{ $creditNote->reason ?: 'ANULACIÓN DE FACTURA' }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- ── DETALLE DE PRODUCTOS / SERVICIOS MODIFICADOS ── -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 8%; text-align: center;">Cant.</th>
                    <th style="width: 45%;">Descripción</th>
                    <th style="width: 11%; text-align: right;">P. Unitario</th>
                    <th style="width: 10%; text-align: right;">Descuento</th>
                    <th style="width: 14%; text-align: right;">Precio Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($creditNote->sale->details as $index => $detail)
                    @php
                        $rate = (float)($detail->tax_rate ?? 15.00);
                        $qty = (float)($detail->quantity ?? 1);
                        $gross = $qty * (float)$detail->price;
                        $disc = (float)($detail->discount ?? 0);
                        $sub = round(($gross - $disc) / (1 + ($rate / 100)), 2);
                        $pUnit = round((float)$detail->price / (1 + ($rate / 100)), 4);
                    @endphp
                    <tr>
                        <td style="font-family: monospace;">{{ $detail->product?->sku ?: str_pad($index + 1, 4, '0', STR_PAD_LEFT) }}</td>
                        <td style="text-align: center; font-weight: 600;">{{ number_format($qty, 2) }}</td>
                        <td>{{ $detail->description }}</td>
                        <td style="text-align: right; font-family: monospace;">${{ number_format($pUnit, 4) }}</td>
                        <td style="text-align: right; font-family: monospace;">${{ number_format($disc, 2) }}</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 600;">${{ number_format($sub, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- ── PIE: INFORMACIÓN ADICIONAL Y TOTALES ── -->
        <table class="layout-table">
            <tr>
                <!-- Columna Izquierda: Info Adicional -->
                <td style="width: 55%; vertical-align: top; padding-right: 4px;">
                    <div class="card-panel">
                        <div class="info-label" style="margin-bottom: 4px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px;">
                            Información Adicional
                        </div>
                        @if($creditNote->sale->client->email)
                            <div class="company-detail"><strong>Email:</strong> {{ $creditNote->sale->client->email }}</div>
                        @endif
                        @if($creditNote->sale->client->phone)
                            <div class="company-detail"><strong>Teléfono:</strong> {{ $creditNote->sale->client->phone }}</div>
                        @endif
                        @if($creditNote->sale->client->address)
                            <div class="company-detail"><strong>Dirección:</strong> {{ $creditNote->sale->client->address }}</div>
                        @endif
                        @if($creditNote->sale->vehicle)
                            <div class="company-detail"><strong>Vehículo / Placa:</strong> {{ $creditNote->sale->vehicle->brand }} {{ $creditNote->sale->vehicle->model }} ({{ $creditNote->sale->vehicle->license_plate }})</div>
                        @endif
                    </div>
                </td>

                <!-- Columna Derecha: Cuadro de Totales -->
                <td style="width: 45%; vertical-align: top; padding-left: 4px;">
                    <table class="totals-table">
                        <tr>
                            <td class="lbl">SUBTOTAL 15%:</td>
                            <td class="val">${{ number_format((float)$creditNote->subtotal_iva_15, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">SUBTOTAL 0%:</td>
                            <td class="val">${{ number_format((float)$creditNote->subtotal_iva_0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">SUBTOTAL SIN IMPUESTOS:</td>
                            <td class="val">${{ number_format((float)$creditNote->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">TOTAL DESCUENTO:</td>
                            <td class="val">${{ number_format((float)$creditNote->sale->details->sum('discount'), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">IVA 15%:</td>
                            <td class="val">${{ number_format((float)$creditNote->tax_amount, 2) }}</td>
                        </tr>
                        <tr class="total-highlight">
                            <td class="lbl total-highlight">VALOR TOTAL MODIFICACIÓN:</td>
                            <td class="val total-highlight">${{ number_format((float)$creditNote->total, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

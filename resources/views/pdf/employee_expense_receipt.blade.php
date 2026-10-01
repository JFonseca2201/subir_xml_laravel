<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receipt['title'] ?? 'Comprobante de Movimiento de Personal' }} - {{ $receipt['doc_number'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm 8mm 4mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 9.5px;
            line-height: 1.3;
            padding: 0;
            width: 100%;
            margin: 0 auto;
        }

        /* ─── BOTONES DE PANTALLA (SOLO VISTA NAVEGADOR) ─── */
        .no-print-toolbar {
            position: fixed;
            top: 12px;
            right: 16px;
            background: rgba(15, 23, 42, 0.92);
            padding: 8px 14px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
            z-index: 99999;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
        }

        .no-print-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 7px 15px;
            border-radius: 5px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .no-print-btn.btn-close {
            background: #475569;
        }

        .no-print-btn:hover {
            opacity: 0.92;
        }

        @media print {
            .no-print-toolbar {
                display: none !important;
            }
            html, body {
                padding: 0;
                margin: 0;
            }
            .voucher-box {
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
            }
        }

        /* ─── CONTENEDOR DE CADA MEDIA HOJA (OCUPA EXACTAMENTE EL 50% DE LA HOJA A4) ─── */
        .voucher-box {
            width: 100%;
            height: 137mm;
            max-height: 137mm;
            box-sizing: border-box;
            page-break-inside: avoid;
            page-break-after: avoid;
            border: 1.2px solid #94a3b8;
            border-radius: 4px;
            padding: 10px 14px;
            background: #ffffff;
            position: relative;
        }

        /* ─── ENCABEZADO ─── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .company-logo-col {
            width: 85px;
            padding-right: 10px;
        }

        .company-logo {
            max-width: 82px;
            max-height: 54px;
            object-fit: contain;
            display: block;
        }

        .logo-monogram {
            width: 52px;
            height: 52px;
            border-radius: 6px;
            background: #1e293b;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            line-height: 52px;
        }

        .company-info-col {
            width: 48%;
            padding-right: 8px;
        }

        .company-name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #0f172a;
            margin-bottom: 2px;
            line-height: 1.15;
        }

        .company-detail {
            font-size: 8.8px;
            color: #334155;
            line-height: 1.25;
        }

        .company-detail-bold {
            font-weight: bold;
            color: #0f172a;
        }

        .doc-header-col {
            width: 44%;
            text-align: right;
        }

        .doc-box {
            border: 1.5px solid #0f172a;
            padding: 5px 10px;
            text-align: center;
            background-color: #f8fafc;
            border-radius: 5px;
        }

        .doc-type-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #0f172a;
        }

        .doc-number {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.4px;
            color: #1d4ed8;
            margin: 2px 0;
        }

        .copy-badge {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .copy-badge-worker {
            background-color: #1e3a8a;
        }

        .copy-badge-business {
            background-color: #047857;
        }

        /* ─── TABLA DE DATOS DEL TRABAJADOR ─── */
        .info-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 6px;
            border: 1.2px solid #cbd5e1;
            font-size: 9.2px;
            background: #ffffff;
        }

        .info-card-table td {
            padding: 4px 7px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-weight: bold;
            color: #334155;
            width: 16%;
            text-transform: uppercase;
            background-color: #f8fafc;
            font-size: 8.8px;
        }

        .info-val {
            color: #0f172a;
            width: 34%;
        }

        .info-val-wide {
            color: #0f172a;
            width: 84%;
        }

        /* ─── TABLA DE CONCEPTO ─── */
        .concept-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 6px;
            font-size: 9.2px;
        }

        .concept-table th {
            font-weight: bold;
            border-top: 1.2px solid #0f172a;
            border-bottom: 1.2px solid #0f172a;
            padding: 4.5px 7px;
            text-transform: uppercase;
            font-size: 8.8px;
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .concept-table td {
            padding: 4.5px 7px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }

        /* ─── CUADRO DE TOTALES ─── */
        .amount-highlight-box {
            background-color: #f8fafc;
            border: 1.2px solid #0f172a;
            border-radius: 4px;
            padding: 5px 10px;
            margin-top: 6px;
            margin-bottom: 6px;
            width: 100%;
        }

        .amount-highlight-table {
            width: 100%;
            border-collapse: collapse;
        }

        .amount-highlight-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .amount-words-col {
            font-size: 8.8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e293b;
            line-height: 1.25;
            padding-right: 10px;
        }

        .amount-num-col {
            text-align: right;
            white-space: nowrap;
        }

        .amount-num-val {
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.3px;
        }

        /* ─── SECCIÓN DE FIRMAS AL PIE DE CADA MEDIA HOJA ─── */
        .footer-table {
            width: calc(100% - 28px);
            border-collapse: collapse;
            position: absolute;
            bottom: 10px;
            left: 14px;
        }

        .footer-table td {
            vertical-align: bottom;
            border: none;
        }

        .signature-box {
            width: 44%;
            text-align: center;
        }

        .signature-space {
            height: 48px; /* Espacio amplio para firma y sello */
            width: 100%;
        }

        .signature-line {
            width: 85%;
            margin: 0 auto 4px auto;
            border-bottom: 1.2px solid #0f172a;
        }

        .signature-title {
            font-size: 8.2px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            margin-bottom: 2px;
        }

        .signature-name {
            font-size: 9.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            line-height: 1.15;
        }

        .signature-subtitle {
            font-size: 8.2px;
            color: #334155;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* ─── SEPARADOR CENTRAL DE CORTE (ENTRE AMBAS COPIAS) ─── */
        .cut-divider {
            width: 100%;
            height: 6mm;
            margin: 0;
            text-align: center;
            page-break-inside: avoid;
            page-break-after: avoid;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cut-dashed-line {
            border-bottom: 1px dashed #64748b;
            width: 100%;
            margin: 2.5mm 0;
            text-align: center;
            line-height: 0.1em;
        }

        .cut-badge {
            background: #ffffff;
            padding: 0 8px;
            font-size: 7px;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    @if(empty($is_pdf))
    <!-- Barra interactiva en navegador (No imprimible, sólo vista HTML) -->
    <div class="no-print-toolbar">
        <button class="no-print-btn" onclick="window.print()">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir Comprobante (2 Copias en Hoja A4)
        </button>
        <button class="no-print-btn btn-close" onclick="window.close()">Cerrar</button>
    </div>
    @endif

    <!-- ========================================== -->
    <!-- COPIA 1: TRABAJADOR (PARTE SUPERIOR) -->
    <!-- ========================================== -->
    <div class="voucher-box">
        <!-- Encabezado de la Empresa -->
        <table class="header-table">
            <tr>
                <td class="company-logo-col">
                    @if(!empty($company['logoBase64']))
                        <img src="{{ $company['logoBase64'] }}" alt="Logo" class="company-logo">
                    @else
                        <div class="logo-monogram">
                            {{ substr($company['name'] ?? 'E', 0, 2) }}
                        </div>
                    @endif
                </td>

                <td class="company-info-col">
                    <div class="company-name">{{ $company['name'] ?? 'EMPRESA' }}</div>
                    @if(!empty($company['trade_name']) && $company['trade_name'] !== ($company['name'] ?? ''))
                        <div class="company-detail company-detail-bold">{{ $company['trade_name'] }}</div>
                    @endif
                    <div class="company-detail"><span class="company-detail-bold">RUC:</span> {{ $company['ruc'] ?? '1790012345001' }}</div>
                    <div class="company-detail"><span class="company-detail-bold">DIR:</span> {{ $company['address'] ?? 'Matriz' }}</div>
                    @if(!empty($company['phone']))
                        <div class="company-detail"><span class="company-detail-bold">TEL:</span> {{ $company['phone'] }}</div>
                    @endif
                </td>

                <td class="doc-header-col">
                    <div class="doc-box">
                        <div class="doc-type-title">{{ $receipt['title'] ?? 'COMPROBANTE DE ENTREGA' }}</div>
                        <div class="doc-number">{{ $receipt['doc_number'] ?? 'N/A' }}</div>
                        <div style="margin-top: 1px;">
                            <span class="copy-badge copy-badge-worker">ORIGINAL: TRABAJADOR</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Datos del Empleado y Transacción -->
        <table class="info-card-table">
            <tr>
                <td class="info-label">TRABAJADOR:</td>
                <td class="info-val" style="font-weight: 800; font-size: 9.8px; color: #0f172a;">
                    {{ $receipt['employee_name'] ?? 'N/A' }}
                </td>
                <td class="info-label">IDENTIFICACIÓN:</td>
                <td class="info-val" style="font-weight: 700;">
                    {{ !empty($receipt['employee_id_card']) ? $receipt['employee_id_card'] : 'N/D' }}
                </td>
            </tr>
            <tr>
                <td class="info-label">TELÉFONO:</td>
                <td class="info-val">
                    {{ !empty($receipt['employee_phone']) ? $receipt['employee_phone'] : 'N/D' }}
                </td>
                <td class="info-label">CORREO / EMAIL:</td>
                <td class="info-val" style="text-transform: lowercase;">
                    {{ !empty($receipt['employee_email']) ? $receipt['employee_email'] : 'N/D' }}
                </td>
            </tr>
            <tr>
                <td class="info-label">FECHA DE PAGO:</td>
                <td class="info-val"><strong>{{ $receipt['date'] ?? date('d/m/Y') }}</strong></td>
                <td class="info-label">FORMA DE PAGO:</td>
                <td class="info-val"><strong>{{ $receipt['payment_method'] ?? 'EFECTIVO' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">CUENTA / CAJA:</td>
                <td class="info-val">{{ $receipt['account_name'] ?? 'Caja General' }}</td>
                <td class="info-label">CARGO / ROL:</td>
                <td class="info-val"><strong>{{ $receipt['employee_position'] ?? 'Personal Operativo' }}</strong></td>
            </tr>
        </table>

        <!-- Detalle de Concepto / Liquidación con Desglose de Adelantos -->
        <table class="concept-table">
            <thead>
                <tr>
                    <th style="width: 25%; text-align: left;">RUBRO / CONCEPTO</th>
                    <th style="width: 55%; text-align: left;">DESCRIPCIÓN Y DETALLE DE ABONOS</th>
                    <th style="width: 20%; text-align: right;">VALOR</th>
                </tr>
            </thead>
            <tbody>
                @if($receipt['type'] === 'payment')
                    <!-- Sueldo Base -->
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;">
                            (+) SUELDO / REMUNERACIÓN
                        </td>
                        <td style="color: #1e293b;">
                            {{ $receipt['description'] ?? 'Pago de nómina del personal' }}
                            @if(!empty($receipt['month_label']))
                                <br><span style="font-size: 8.5px; color: #475569;">Período Laboral: <strong>{{ $receipt['month_label'] }}</strong></span>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #0f172a;">
                            ${{ number_format((float)($receipt['base_salary'] ?? $receipt['amount']), 2) }}
                        </td>
                    </tr>

                    <!-- Descuento de Adelantos / Anticipos -->
                    @if(isset($receipt['advances_deducted']) && $receipt['advances_deducted'] > 0)
                    <tr style="background-color: #fef2f2;">
                        <td style="font-weight: bold; color: #b91c1c;">
                            (-) DESCUENTO ADELANTOS
                        </td>
                        <td style="color: #991b1b; font-size: 8.5px; line-height: 1.3;">
                            @if(!empty($receipt['advances_items']) && count($receipt['advances_items']) > 0)
                                @foreach($receipt['advances_items'] as $advItem)
                                    <div>• <strong>{{ $advItem['date'] }}</strong> ({{ $advItem['reference'] }}): ${{ number_format($advItem['amount'], 2) }} - <em>{{ $advItem['reason'] }}</em></div>
                                @endforeach
                            @else
                                Anticipos de sueldo descontados en esta nómina
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #b91c1c;">
                            -${{ number_format((float)$receipt['advances_deducted'], 2) }}
                        </td>
                    </tr>
                    @endif
                @else
                    <!-- Comprobante de Adelanto Directo -->
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;">
                            {{ $receipt['reason'] ?? 'Adelanto de Sueldo' }}
                        </td>
                        <td style="color: #1e293b;">
                            {{ $receipt['description'] ?? 'Anticipo de remuneración a descontar en el próximo rol de pagos' }}
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #0f172a;">
                            ${{ number_format((float)($receipt['amount'] ?? 0), 2) }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- Cuadro de Valor Total en Números y Letras -->
        <div class="amount-highlight-box">
            <table class="amount-highlight-table">
                <tr>
                    <td class="amount-words-col">
                        SON: {{ $receipt['amount_in_words'] ?? '' }}
                    </td>
                    <td class="amount-num-col">
                        <span style="font-size: 9px; font-weight: bold; text-transform: uppercase;">
                            {{ $receipt['type'] === 'payment' ? 'NETO A PAGAR:' : 'VALOR TOTAL:' }}
                        </span>
                        <span class="amount-num-val">${{ number_format((float)($receipt['amount'] ?? 0), 2) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sección de Firmas (Ubicadas en la base / pie de la primera media hoja) -->
        <table class="footer-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">AUTORIZADO POR</div>
                    <div class="signature-name">{{ $receipt['authorizer_name'] ?? 'JUAN DIEGO FONSECA YUPA' }}</div>
                    <div class="signature-subtitle">{{ $receipt['authorizer_position'] ?? 'GERENTE GENERAL' }}</div>
                </td>

                <td style="width: 12%;"></td>

                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">RECIBÍ CONFORME (TRABAJADOR)</div>
                    <div class="signature-name">{{ $receipt['employee_name'] ?? 'TRABAJADOR' }}</div>
                    <div class="signature-subtitle">
                        @if(!empty($receipt['employee_id_card']))
                            C.I.: {{ $receipt['employee_id_card'] }}
                        @else
                            FIRMA Y CÉDULA
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ========================================== -->
    <!-- LÍNEA DE CORTE CENTRAL -->
    <!-- ========================================== -->
    <div class="cut-divider">
        <div class="cut-dashed-line">
            <span class="cut-badge">✂ CORTE AQUÍ - SEPARACIÓN DE COMPROBANTES (MEDIA HOJA A4) ✂</span>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- COPIA 2: NEGOCIO / EMPRESA (PARTE INFERIOR) -->
    <!-- ========================================== -->
    <div class="voucher-box">
        <!-- Encabezado de la Empresa -->
        <table class="header-table">
            <tr>
                <td class="company-logo-col">
                    @if(!empty($company['logoBase64']))
                        <img src="{{ $company['logoBase64'] }}" alt="Logo" class="company-logo">
                    @else
                        <div class="logo-monogram">
                            {{ substr($company['name'] ?? 'E', 0, 2) }}
                        </div>
                    @endif
                </td>

                <td class="company-info-col">
                    <div class="company-name">{{ $company['name'] ?? 'EMPRESA' }}</div>
                    @if(!empty($company['trade_name']) && $company['trade_name'] !== ($company['name'] ?? ''))
                        <div class="company-detail company-detail-bold">{{ $company['trade_name'] }}</div>
                    @endif
                    <div class="company-detail"><span class="company-detail-bold">RUC:</span> {{ $company['ruc'] ?? '1790012345001' }}</div>
                    <div class="company-detail"><span class="company-detail-bold">DIR:</span> {{ $company['address'] ?? 'Matriz' }}</div>
                    @if(!empty($company['phone']))
                        <div class="company-detail"><span class="company-detail-bold">TEL:</span> {{ $company['phone'] }}</div>
                    @endif
                </td>

                <td class="doc-header-col">
                    <div class="doc-box">
                        <div class="doc-type-title">{{ $receipt['title'] ?? 'COMPROBANTE DE ENTREGA' }}</div>
                        <div class="doc-number">{{ $receipt['doc_number'] ?? 'N/A' }}</div>
                        <div style="margin-top: 1px;">
                            <span class="copy-badge copy-badge-business">COPIA: NEGOCIO / ARCHIVO</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Datos del Empleado y Transacción -->
        <table class="info-card-table">
            <tr>
                <td class="info-label">TRABAJADOR:</td>
                <td class="info-val" style="font-weight: 800; font-size: 9.8px; color: #0f172a;">
                    {{ $receipt['employee_name'] ?? 'N/A' }}
                </td>
                <td class="info-label">IDENTIFICACIÓN:</td>
                <td class="info-val" style="font-weight: 700;">
                    {{ !empty($receipt['employee_id_card']) ? $receipt['employee_id_card'] : 'N/D' }}
                </td>
            </tr>
            <tr>
                <td class="info-label">TELÉFONO:</td>
                <td class="info-val">
                    {{ !empty($receipt['employee_phone']) ? $receipt['employee_phone'] : 'N/D' }}
                </td>
                <td class="info-label">CORREO / EMAIL:</td>
                <td class="info-val" style="text-transform: lowercase;">
                    {{ !empty($receipt['employee_email']) ? $receipt['employee_email'] : 'N/D' }}
                </td>
            </tr>
            <tr>
                <td class="info-label">FECHA DE PAGO:</td>
                <td class="info-val"><strong>{{ $receipt['date'] ?? date('d/m/Y') }}</strong></td>
                <td class="info-label">FORMA DE PAGO:</td>
                <td class="info-val"><strong>{{ $receipt['payment_method'] ?? 'EFECTIVO' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">CUENTA / CAJA:</td>
                <td class="info-val">{{ $receipt['account_name'] ?? 'Caja General' }}</td>
                <td class="info-label">CARGO / ROL:</td>
                <td class="info-val"><strong>{{ $receipt['employee_position'] ?? 'Personal Operativo' }}</strong></td>
            </tr>
        </table>

        <!-- Detalle de Concepto / Liquidación con Desglose de Adelantos -->
        <table class="concept-table">
            <thead>
                <tr>
                    <th style="width: 25%; text-align: left;">RUBRO / CONCEPTO</th>
                    <th style="width: 55%; text-align: left;">DESCRIPCIÓN Y DETALLE DE ABONOS</th>
                    <th style="width: 20%; text-align: right;">VALOR</th>
                </tr>
            </thead>
            <tbody>
                @if($receipt['type'] === 'payment')
                    <!-- Sueldo Base -->
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;">
                            (+) SUELDO / REMUNERACIÓN
                        </td>
                        <td style="color: #1e293b;">
                            {{ $receipt['description'] ?? 'Pago de nómina del personal' }}
                            @if(!empty($receipt['month_label']))
                                <br><span style="font-size: 8.5px; color: #475569;">Período Laboral: <strong>{{ $receipt['month_label'] }}</strong></span>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #0f172a;">
                            ${{ number_format((float)($receipt['base_salary'] ?? $receipt['amount']), 2) }}
                        </td>
                    </tr>

                    <!-- Descuento de Adelantos / Anticipos -->
                    @if(isset($receipt['advances_deducted']) && $receipt['advances_deducted'] > 0)
                    <tr style="background-color: #fef2f2;">
                        <td style="font-weight: bold; color: #b91c1c;">
                            (-) DESCUENTO ADELANTOS
                        </td>
                        <td style="color: #991b1b; font-size: 8.5px; line-height: 1.3;">
                            @if(!empty($receipt['advances_items']) && count($receipt['advances_items']) > 0)
                                @foreach($receipt['advances_items'] as $advItem)
                                    <div>• <strong>{{ $advItem['date'] }}</strong> ({{ $advItem['reference'] }}): ${{ number_format($advItem['amount'], 2) }} - <em>{{ $advItem['reason'] }}</em></div>
                                @endforeach
                            @else
                                Anticipos de sueldo descontados en esta nómina
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #b91c1c;">
                            -${{ number_format((float)$receipt['advances_deducted'], 2) }}
                        </td>
                    </tr>
                    @endif
                @else
                    <!-- Comprobante de Adelanto Directo -->
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;">
                            {{ $receipt['reason'] ?? 'Adelanto de Sueldo' }}
                        </td>
                        <td style="color: #1e293b;">
                            {{ $receipt['description'] ?? 'Anticipo de remuneración a descontar en el próximo rol de pagos' }}
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 11px; color: #0f172a;">
                            ${{ number_format((float)($receipt['amount'] ?? 0), 2) }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- Cuadro de Valor Total en Números y Letras -->
        <div class="amount-highlight-box">
            <table class="amount-highlight-table">
                <tr>
                    <td class="amount-words-col">
                        SON: {{ $receipt['amount_in_words'] ?? '' }}
                    </td>
                    <td class="amount-num-col">
                        <span style="font-size: 9px; font-weight: bold; text-transform: uppercase;">
                            {{ $receipt['type'] === 'payment' ? 'NETO A PAGAR:' : 'VALOR TOTAL:' }}
                        </span>
                        <span class="amount-num-val">${{ number_format((float)($receipt['amount'] ?? 0), 2) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sección de Firmas (Ubicadas en la base / pie final de la hoja A4) -->
        <table class="footer-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">AUTORIZADO POR</div>
                    <div class="signature-name">{{ $receipt['authorizer_name'] ?? 'JUAN DIEGO FONSECA YUPA' }}</div>
                    <div class="signature-subtitle">{{ $receipt['authorizer_position'] ?? 'GERENTE GENERAL' }}</div>
                </td>

                <td style="width: 12%;"></td>

                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">RECIBÍ CONFORME (TRABAJADOR)</div>
                    <div class="signature-name">{{ $receipt['employee_name'] ?? 'TRABAJADOR' }}</div>
                    <div class="signature-subtitle">
                        @if(!empty($receipt['employee_id_card']))
                            C.I.: {{ $receipt['employee_id_card'] }}
                        @else
                            FIRMA Y CÉDULA
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($auto_print))
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
    @endif
</body>
</html>

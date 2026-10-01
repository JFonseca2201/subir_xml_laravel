<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receipt['title'] ?? 'Comprobante de Movimiento de Personal' }} - {{ $receipt['doc_number'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait; /* Hoja A4 en vertical */
            margin: 6mm 8mm 6mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, Consolas, 'Lucida Console', Monaco, monospace;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            background-color: #ffffff;
            color: #111111;
            font-size: 10px;
            line-height: 1.18;
            padding: 0;
            width: 100%;
            max-width: 195mm;
            margin: 0 auto;
        }

        /* ─── CONTENEDOR DE MEDIA HOJA A4 VERTICAL (EXACTO 135MM) ─── */
        .ticket-wrapper {
            position: relative;
            width: 100%;
            max-width: 100%;
            height: 135mm;
            max-height: 135mm;
            padding-right: 20px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        /* ─── BOTONES DE PANTALLA (NO IMPRIMIR) ─── */
        .no-print-toolbar {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(15, 23, 42, 0.9);
            padding: 8px 14px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .no-print-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            font-family: Arial, sans-serif;
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
            opacity: 0.9;
        }

        @media print {
            .no-print-toolbar {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
        }

        /* ─── HEADER PRINCIPAL ─── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-logo-col {
            width: 65px;
            padding-right: 8px;
        }

        .company-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            display: block;
        }

        .logo-monogram {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: 2px solid #222;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: -1px;
            color: #222;
        }

        .company-info-col {
            width: 52%;
            padding-right: 8px;
        }

        .company-name {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #000000;
            margin-bottom: 2px;
            line-height: 1.15;
        }

        .company-detail {
            font-size: 9px;
            color: #222222;
            line-height: 1.2;
        }

        .company-detail-bold {
            font-weight: bold;
        }

        .doc-header-col {
            width: 38%;
            text-align: right;
        }

        .doc-box {
            border: 1.5px solid #111;
            padding: 5px 8px;
            text-align: center;
            background-color: #fafafa;
            border-radius: 4px;
        }

        .doc-type-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #000;
            margin-bottom: 2px;
        }

        .doc-number {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #1e3a8a;
        }

        .branch-tag {
            font-size: 9px;
            font-weight: bold;
            margin-top: 3px;
            text-transform: uppercase;
            color: #444;
        }

        /* ─── TABLA DE DATOS DEL TRABAJADOR Y MOVIMIENTO ─── */
        .info-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 5px;
            border: 1px solid #222;
            font-size: 9.2px;
        }

        .info-card-table td {
            padding: 3px 6px;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
        }

        .info-card-table tr:last-child td {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #000;
            width: 18%;
            text-transform: uppercase;
        }

        .info-val {
            color: #111;
            width: 32%;
            text-transform: uppercase;
        }

        .info-val-wide {
            color: #111;
            width: 82%;
            text-transform: uppercase;
        }

        /* ─── TABLA DE DESGLOSE / CONCEPTO ─── */
        .concept-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            margin-bottom: 4px;
            font-size: 9.2px;
        }

        .concept-table th {
            font-weight: bold;
            border-top: 1.5px solid #111;
            border-bottom: 1.5px solid #111;
            padding: 3px 4px;
            text-transform: uppercase;
            font-size: 9px;
            background-color: #f5f5f5;
        }

        .concept-table td {
            padding: 3.5px 4px;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        .amount-highlight-box {
            background-color: #f8fafc;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 4px;
            margin-bottom: 4px;
            display: table;
            width: 100%;
        }

        .amount-words-col {
            display: table-cell;
            vertical-align: middle;
            font-size: 8.8px;
            font-weight: bold;
            text-transform: uppercase;
            width: 68%;
            padding-right: 8px;
            line-height: 1.25;
        }

        .amount-num-col {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 32%;
        }

        .amount-num-val {
            font-size: 15px;
            font-weight: 900;
            color: #000;
            letter-spacing: 0.5px;
        }

        /* ─── FOOTER (FIRMAS - FIJADO AL CORTE INFERIOR DE 135MM) ─── */
        .footer-table {
            position: absolute;
            bottom: 0px;
            left: 0px;
            width: calc(100% - 20px);
            border-collapse: collapse;
            margin: 0;
        }

        .footer-table td {
            vertical-align: bottom;
        }

        .signature-box {
            width: 46%;
            text-align: center;
        }

        .signature-space {
            height: 36px;
            width: 100%;
        }

        .signature-line {
            width: 90%;
            margin: 0 auto 3px auto;
            border-bottom: 1.5px solid #000;
        }

        .signature-title {
            font-size: 8.8px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .signature-subtitle {
            font-size: 7.8px;
            color: #444;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* ─── TEXTO LATERAL VERTICAL ─── */
        .lateral-watermark {
            position: absolute;
            right: 0px;
            top: 0px;
            bottom: 0px;
            height: 135mm;
            max-height: 135mm;
            width: 14px;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6.5px;
            color: #777777;
            letter-spacing: 0.6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1;
        }

        /* ─── LÍNEA DE CORTE SIMPLE ─── */
        .cut-line {
            width: 100%;
            border-bottom: 1px dashed #9ca3af;
            margin-top: 3mm;
        }
    </style>
</head>

<body>
    <!-- Barra interactiva en navegador (No imprimible) -->
    <div class="no-print-toolbar">
        <button class="no-print-btn" onclick="window.print()">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir Comprobante
        </button>
        <button class="no-print-btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <!-- ─── CUERPO MEDIA HOJA (135MM) ─── -->
    <div class="ticket-wrapper">
        <!-- Texto vertical lateral de seguridad y trazabilidad -->
        <div class="lateral-watermark">
            COMPROBANTE EMITIDO EL {{ date('d/m/Y H:i') }} - SISTEMA DE CONTROL DE PERSONAL Y FINANZAS
        </div>

        <!-- Encabezado de la Empresa -->
        <table class="header-table">
            <tr>
                <!-- Logo -->
                <td class="company-logo-col">
                    @if(!empty($company['logoBase64']))
                        <img src="{{ $company['logoBase64'] }}" alt="Logo" class="company-logo">
                    @else
                        <div class="logo-monogram">
                            {{ substr($company['name'] ?? 'E', 0, 2) }}
                        </div>
                    @endif
                </td>

                <!-- Datos Empresa -->
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

                <!-- Cuadro de Identificación del Comprobante -->
                <td class="doc-header-col">
                    <div class="doc-box">
                        <div class="doc-type-title">{{ $receipt['title'] ?? 'COMPROBANTE DE ENTREGA' }}</div>
                        <div class="doc-number">{{ $receipt['doc_number'] ?? 'N/A' }}</div>
                        <div class="branch-tag">SUCURSAL: {{ $company['sucursal_name'] ?? 'MATRIZ' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Datos del Empleado y Transacción -->
        <table class="info-card-table">
            <tr>
                <td class="info-label">TRABAJADOR:</td>
                <td class="info-val-wide" colspan="3">
                    <strong>{{ $receipt['employee_name'] ?? 'N/A' }}</strong> 
                    @if(!empty($receipt['employee_id_card']))
                        &nbsp;&nbsp;[C.I.: {{ $receipt['employee_id_card'] }}]
                    @endif
                </td>
            </tr>
            <tr>
                <td class="info-label">FECHA:</td>
                <td class="info-val"><strong>{{ $receipt['date'] ?? date('d/m/Y') }}</strong></td>
                <td class="info-label">FORMA PAGO:</td>
                <td class="info-val"><strong>{{ $receipt['payment_method'] ?? 'EFECTIVO' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">CUENTA/CAJA:</td>
                <td class="info-val">{{ $receipt['account_name'] ?? 'Caja General' }}</td>
                <td class="info-label">CARGO/ROL:</td>
                <td class="info-val">{{ $receipt['employee_position'] ?? 'Personal Operativo' }}</td>
            </tr>
        </table>

        <!-- Detalle de Concepto / Motivo y Descripción -->
        <table class="concept-table">
            <thead>
                <tr>
                    <th style="width: 25%; text-align: left;">MOTIVO / CONCEPTO</th>
                    <th style="width: 55%; text-align: left;">DESCRIPCIÓN Y OBSERVACIONES</th>
                    <th style="width: 20%; text-align: right;">VALOR RECIBIDO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight: bold;">
                        {{ $receipt['reason'] ?? ($receipt['type'] === 'payment' ? 'Pago de Nómina / Sueldo' : 'Adelanto de Sueldo') }}
                    </td>
                    <td>
                        {{ $receipt['description'] ?? 'Entrega de valores en efectivo/transferencia al trabajador' }}
                        @if($receipt['type'] === 'payment' && !empty($receipt['month_label']))
                            <br><span style="font-size: 8.5px; color: #333;">(Período Correspondiente: <strong>{{ $receipt['month_label'] }}</strong>)</span>
                        @endif
                        @if($receipt['type'] === 'payment' && isset($receipt['advances_deducted']) && $receipt['advances_deducted'] > 0)
                            <br><span style="font-size: 8.2px; color: #555;">[Sueldo Base: ${{ number_format($receipt['base_salary'] ?? 0, 2) }} - Adelantos Descontados: ${{ number_format($receipt['advances_deducted'], 2) }}]</span>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: bold; font-size: 10px;">
                        ${{ number_format((float)($receipt['amount'] ?? 0), 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cuadro de Valor Total en Números y Letras -->
        <div class="amount-highlight-box">
            <div class="amount-words-col">
                SON: {{ $receipt['amount_in_words'] ?? '' }}
            </div>
            <div class="amount-num-col">
                <span style="font-size: 9px; font-weight: bold; text-transform: uppercase;">TOTAL: </span>
                <span class="amount-num-val">${{ number_format((float)($receipt['amount'] ?? 0), 2) }}</span>
            </div>
        </div>

        <!-- Sección de Firmas (Fijado al corte inferior de 135mm) -->
        <table class="footer-table">
            <tr>
                <!-- Firma Empleador / Caja -->
                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">AUTORIZADO POR / EMISOR</div>
                    <div class="signature-subtitle">{{ $company['name'] ?? 'ADMINISTRACIÓN' }}</div>
                </td>

                <td style="width: 8%;"></td>

                <!-- Firma Trabajador -->
                <td class="signature-box">
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">RECIBÍ CONFORME (TRABAJADOR)</div>
                    <div class="signature-subtitle">{{ $receipt['employee_name'] ?? 'FIRMA Y CÉDULA' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Línea de corte simple que marca exactamente la media hoja A4 -->
    <div class="cut-line"></div>
</body>
</html>

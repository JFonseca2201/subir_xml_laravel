<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receipt['title'] ?? 'Comprobante de Movimiento de Personal' }} - {{ $receipt['doc_number'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm 8mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 9.5px;
            line-height: 1.25;
            padding: 0;
            width: 100%;
            margin: 0 auto;
        }

        /* ─── CONTENEDOR DE MEDIA HOJA A4 ─── */
        .ticket-wrapper {
            width: 100%;
            page-break-inside: avoid;
        }

        /* ─── BOTONES DE PANTALLA (NO IMPRIMIR) ─── */
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
            transition: background 0.2s;
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
            body {
                padding: 0;
                margin: 0;
            }
        }

        /* ─── HEADER PRINCIPAL ─── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-logo-col {
            width: 65px;
            padding-right: 8px;
        }

        .company-logo {
            max-width: 60px;
            max-height: 55px;
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
            width: 52%;
            padding-right: 8px;
        }

        .company-name {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #0f172a;
            margin-bottom: 1px;
            line-height: 1.15;
        }

        .company-detail {
            font-size: 8.5px;
            color: #334155;
            line-height: 1.25;
        }

        .company-detail-bold {
            font-weight: bold;
            color: #0f172a;
        }

        .doc-header-col {
            width: 38%;
            text-align: right;
        }

        .doc-box {
            border: 1.5px solid #0f172a;
            padding: 5px 8px;
            text-align: center;
            background-color: #f8fafc;
            border-radius: 4px;
        }

        .doc-type-title {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .doc-number {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #1d4ed8;
        }

        .branch-tag {
            font-size: 8px;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            color: #475569;
        }

        /* ─── TABLA DE DATOS DEL TRABAJADOR Y MOVIMIENTO ─── */
        .info-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 5px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
            background: #ffffff;
        }

        .info-card-table td {
            padding: 3px 6px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-weight: bold;
            color: #334155;
            width: 16%;
            text-transform: uppercase;
            background-color: #f8fafc;
            font-size: 8.5px;
        }

        .info-val {
            color: #0f172a;
            width: 34%;
        }

        .info-val-wide {
            color: #0f172a;
            width: 84%;
        }

        /* ─── TABLA DE DESGLOSE / CONCEPTO ─── */
        .concept-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            margin-bottom: 4px;
            font-size: 9px;
        }

        .concept-table th {
            font-weight: bold;
            border-top: 1.5px solid #0f172a;
            border-bottom: 1.5px solid #0f172a;
            padding: 3.5px 5px;
            text-transform: uppercase;
            font-size: 8.5px;
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .concept-table td {
            padding: 4px 5px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }

        .amount-highlight-box {
            background-color: #f8fafc;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 4px;
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
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e293b;
            line-height: 1.25;
            padding-right: 8px;
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

        /* ─── FOOTER (FIRMAS - FLUJO NATURAL) ─── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        .footer-table td {
            vertical-align: bottom;
            border: none;
        }

        .signature-box {
            width: 46%;
            text-align: center;
        }

        .signature-space {
            height: 32px;
            width: 100%;
        }

        .signature-line {
            width: 85%;
            margin: 0 auto 3px auto;
            border-bottom: 1.2px solid #0f172a;
        }

        .signature-title {
            font-size: 8.5px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .signature-subtitle {
            font-size: 7.8px;
            color: #475569;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* ─── LÍNEA DE CORTE SIMPLE QUE MARCA MEDIA HOJA ─── */
        .cut-line-wrapper {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 4px;
            text-align: center;
        }

        .cut-line {
            width: 100%;
            border-bottom: 1px dashed #94a3b8;
            position: relative;
        }

        .cut-label {
            font-size: 7.5px;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-top: 2px;
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
            Imprimir Comprobante
        </button>
        <button class="no-print-btn btn-close" onclick="window.close()">Cerrar</button>
    </div>
    @endif

    <!-- ─── CUERPO MEDIA HOJA ─── -->
    <div class="ticket-wrapper">
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
                    <td style="font-weight: bold; color: #0f172a;">
                        {{ $receipt['reason'] ?? ($receipt['type'] === 'payment' ? 'Pago de Nómina / Sueldo' : 'Adelanto de Sueldo') }}
                    </td>
                    <td style="color: #1e293b;">
                        {{ $receipt['description'] ?? 'Entrega de valores en efectivo/transferencia al trabajador' }}
                        @if($receipt['type'] === 'payment' && !empty($receipt['month_label']))
                            <br><span style="font-size: 8.2px; color: #475569;">(Período Correspondiente: <strong>{{ $receipt['month_label'] }}</strong>)</span>
                        @endif
                        @if($receipt['type'] === 'payment' && isset($receipt['advances_deducted']) && $receipt['advances_deducted'] > 0)
                            <br><span style="font-size: 8px; color: #475569;">[Sueldo Base: ${{ number_format($receipt['base_salary'] ?? 0, 2) }} - Adelantos Descontados: ${{ number_format($receipt['advances_deducted'], 2) }}]</span>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: bold; font-size: 10.5px; color: #0f172a;">
                        ${{ number_format((float)($receipt['amount'] ?? 0), 2) }}
                    </td>
                </tr>
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
                        <span style="font-size: 9px; font-weight: bold; text-transform: uppercase;">TOTAL: </span>
                        <span class="amount-num-val">${{ number_format((float)($receipt['amount'] ?? 0), 2) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sección de Firmas -->
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
    <div class="cut-line-wrapper">
        <div class="cut-line"></div>
        <div class="cut-label">✂ LÍNEA DE CORTE - MEDIA HOJA A4</div>
    </div>

    @if(!empty($auto_print))
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 350);
        });
    </script>
    @endif
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Adelanto - {{ $advance->reference ?? ('ADEL-EMP-' . str_pad($advance->id, 5, '0', STR_PAD_LEFT)) }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.35;
            padding: 0;
            width: 100%;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-logo-col {
            width: 75px;
            padding-right: 10px;
        }

        .company-logo {
            max-width: 70px;
            max-height: 65px;
            object-fit: contain;
        }

        .company-info-col {
            width: 50%;
        }

        .company-name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .company-detail {
            font-size: 9px;
            color: #334155;
            line-height: 1.3;
        }

        .doc-header-col {
            width: 40%;
            text-align: right;
        }

        .doc-box {
            border: 2px solid #0f172a;
            padding: 8px 12px;
            text-align: center;
            background-color: #f8fafc;
            border-radius: 6px;
            display: inline-block;
            min-width: 200px;
        }

        .doc-type-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #0f172a;
        }

        .doc-number {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.6px;
            color: #1d4ed8;
            margin: 2px 0;
        }

        .info-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
        }

        .info-card-table td {
            padding: 6px 8px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-weight: bold;
            color: #334155;
            width: 20%;
            text-transform: uppercase;
            background-color: #f8fafc;
            font-size: 9px;
        }

        .info-val {
            color: #0f172a;
            width: 30%;
        }

        .info-val-wide {
            color: #0f172a;
            width: 80%;
        }

        .concept-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
            font-size: 9.5px;
        }

        .concept-table th {
            font-weight: bold;
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            padding: 6px 8px;
            text-transform: uppercase;
            font-size: 9px;
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .concept-table td {
            padding: 8px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }

        .amount-highlight-box {
            background-color: #f8fafc;
            border: 2px solid #0f172a;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 10px;
            margin-bottom: 24px;
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
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e293b;
            line-height: 1.3;
            padding-right: 12px;
        }

        .amount-num-col {
            text-align: right;
            white-space: nowrap;
        }

        .amount-num-val {
            font-size: 17px;
            font-weight: 900;
            color: #0f172a;
        }

        .terms-box {
            border: 1px solid #cbd5e1;
            background-color: #fafafa;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 30px;
            line-height: 1.35;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
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
            height: 50px;
            width: 100%;
        }

        .signature-line {
            width: 85%;
            margin: 0 auto 4px auto;
            border-bottom: 1.5px solid #0f172a;
        }

        .signature-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }

        .signature-subtitle {
            font-size: 8.5px;
            color: #475569;
            text-transform: uppercase;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="company-logo-col">
                @if(!empty($company_logo_base64))
                    <img src="{{ $company_logo_base64 }}" alt="Logo" class="company-logo">
                @endif
            </td>
            <td class="company-info-col">
                <div class="company-name">{{ $company_name }}</div>
                @if(!empty($company_trade_name) && $company_trade_name !== $company_name)
                    <div class="company-detail" style="font-weight: bold;">{{ $company_trade_name }}</div>
                @endif
                <div class="company-detail"><strong>RUC:</strong> {{ $company_ruc }}</div>
                <div class="company-detail"><strong>DIR:</strong> {{ $company_address }}</div>
                @if(!empty($company_phone))
                    <div class="company-detail"><strong>TEL:</strong> {{ $company_phone }}</div>
                @endif
            </td>
            <td class="doc-header-col">
                <div class="doc-box">
                    <div class="doc-type-title">COMPROBANTE DE ADELANTO</div>
                    <div class="doc-number">{{ $doc_number }}</div>
                    <div style="font-size: 8.5px; font-weight: bold; color: #475569; margin-top: 2px;">
                        SUCURSAL: {{ $sucursal_name }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="info-card-table">
        <tr>
            <td class="info-label">TRABAJADOR:</td>
            <td class="info-val-wide" colspan="3">
                <strong>{{ $employee_name }}</strong>
                @if(!empty($employee_id_card))
                    &nbsp;&nbsp;[C.I.: {{ $employee_id_card }}]
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">FECHA ADELANTO:</td>
            <td class="info-val"><strong>{{ $advance_date }}</strong></td>
            <td class="info-label">FORMA DE PAGO:</td>
            <td class="info-val"><strong>{{ $payment_method }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">CUENTA / CAJA:</td>
            <td class="info-val">{{ $account_name }}</td>
            <td class="info-label">CARGO / PUESTO:</td>
            <td class="info-val">{{ $employee_position }}</td>
        </tr>
    </table>

    <table class="concept-table">
        <thead>
            <tr>
                <th style="width: 25%; text-align: left;">CONCEPTO</th>
                <th style="width: 55%; text-align: left;">DESCRIPCIÓN Y OBSERVACIONES</th>
                <th style="width: 20%; text-align: right;">VALOR DEL ADELANTO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold; color: #0f172a;">
                    {{ $advance->reason ?: 'Adelanto de Sueldo / Anticipo' }}
                </td>
                <td style="color: #1e293b;">
                    {{ $advance->description ?: 'Anticipo de remuneración económica a favor del trabajador.' }}
                </td>
                <td style="text-align: right; font-weight: bold; font-size: 12px; color: #0f172a;">
                    ${{ number_format($amount, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="amount-highlight-box">
        <table class="amount-highlight-table">
            <tr>
                <td class="amount-words-col">
                    SON: {{ $amount_in_words }}
                </td>
                <td class="amount-num-col">
                    <span style="font-size: 10px; font-weight: bold; text-transform: uppercase;">TOTAL: </span>
                    <span class="amount-num-val">${{ number_format($amount, 2) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="terms-box">
        <strong>DECLARACIÓN Y CONVENIO DE DESCUENTO:</strong><br>
        El trabajador declara haber recibido la suma indicada en concepto de adelanto de sueldo / anticipo quincenal, y autoriza expresamente a la empresa empleadora a descontar dicho valor de su respectiva remuneración mensual en el rol de pagos correspondiente.
    </div>

    <table class="footer-table">
        <tr>
            <td class="signature-box">
                <div class="signature-space"></div>
                <div class="signature-line"></div>
                <div class="signature-title">AUTORIZADO POR / EMISOR</div>
                <div class="signature-subtitle">{{ $company_name }}</div>
            </td>

            <td style="width: 12%;"></td>

            <td class="signature-box">
                <div class="signature-space"></div>
                <div class="signature-line"></div>
                <div class="signature-title">RECIBÍ CONFORME (TRABAJADOR)</div>
                <div class="signature-subtitle">{{ $employee_name }}</div>
            </td>
        </tr>
    </table>
</body>
</html>

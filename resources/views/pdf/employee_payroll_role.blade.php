<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Rol de Pagos - {{ $employee->first_name }} {{ $employee->last_name }} - {{ $month_label }}</title>
    <style>
        @page {
            margin: 25px 30px;
            size: A4 portrait;
        }

        * {
            font-family: 'Helvetica', Arial, sans-serif !important;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', Arial, sans-serif !important;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
        }

        .header-logo {
            width: 35%;
            vertical-align: middle;
        }

        .header-logo img {
            max-height: 70px;
            max-width: 200px;
        }

        .header-company {
            width: 35%;
            vertical-align: middle;
            text-align: left;
            padding-left: 10px;
        }

        .company-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 3px 0;
        }

        .company-info {
            font-size: 9.5px;
            color: #475569;
            line-height: 1.3;
        }

        .header-doc {
            width: 30%;
            vertical-align: middle;
            text-align: right;
        }

        .doc-badge {
            background-color: #0f172a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
            border-radius: 4px;
            margin-bottom: 4px;
        }

        .doc-number {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
        }

        .doc-period {
            font-size: 10px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Section Titles */
        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 4px 8px;
            border-left: 3px solid #2563eb;
            margin-top: 10px;
            margin-bottom: 8px;
            border-radius: 2px;
        }

        /* Info Grid */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .info-table td {
            padding: 5px 8px;
            font-size: 10px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-label {
            font-weight: bold;
            color: #64748b;
            width: 18%;
        }

        .info-val {
            color: #0f172a;
            width: 32%;
            font-weight: 500;
        }

        /* Financial Breakdown Tables */
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .breakdown-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
        }

        .breakdown-table td {
            padding: 6px 8px;
            font-size: 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .breakdown-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .col-ingresos {
            width: 48%;
            vertical-align: top;
        }

        .col-spacer {
            width: 4%;
        }

        .col-egresos {
            width: 48%;
            vertical-align: top;
        }

        .table-mini {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }

        .table-mini th {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            padding: 5px 8px;
            text-transform: uppercase;
        }

        .table-mini th.th-egresos {
            background-color: #b91c1c;
        }

        .table-mini th.th-ingresos {
            background-color: #15803d;
        }

        .table-mini td {
            padding: 5px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-mini tr.total-row td {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 1px solid #cbd5e1;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Net Amount Banner */
        .net-banner {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            background-color: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 6px;
        }

        .net-banner td {
            padding: 10px 14px;
            vertical-align: middle;
        }

        .net-title {
            font-size: 12px;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            margin: 0;
        }

        .net-letters {
            font-size: 9px;
            color: #15803d;
            margin-top: 3px;
            font-style: italic;
        }

        .net-amount {
            font-size: 20px;
            font-weight: bold;
            color: #166534;
            text-align: right;
        }

        /* Legal Conformity */
        .conformity-text {
            font-size: 8.5px;
            color: #64748b;
            text-align: justify;
            margin: 10px 0 25px 0;
            line-height: 1.35;
            padding: 6px 10px;
            background-color: #f8fafc;
            border-left: 2px solid #cbd5e1;
            border-radius: 2px;
        }

        /* Signatures Section */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .signatures-table td {
            width: 46%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 10px;
        }

        .sig-spacer {
            width: 8% !important;
        }

        .signature-line {
            border-top: 1px solid #475569;
            margin-bottom: 6px;
            margin-top: 40px;
        }

        .sig-name {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }

        .sig-detail {
            font-size: 9px;
            color: #64748b;
        }

        /* Sello de la Empresa (Graphic Stamp) */
        .company-stamp {
            border: 2px dashed #1e40af;
            border-radius: 8px;
            padding: 8px 12px;
            display: inline-block;
            background-color: #eff6ff;
            color: #1e3a8a;
            text-align: center;
            margin-bottom: 5px;
            width: 190px;
        }

        .stamp-company {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1e40af;
        }

        .stamp-status {
            font-size: 13px;
            font-weight: bold;
            color: #dc2626;
            margin: 2px 0;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            border-top: 1px solid #93c5fd;
            border-bottom: 1px solid #93c5fd;
            padding: 1px 0;
        }

        .stamp-ruc {
            font-size: 8px;
            font-weight: bold;
            color: #1e40af;
        }

        .stamp-date {
            font-size: 7.5px;
            color: #3b82f6;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Encabezado Institucional -->
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    @if (!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="Logo Empresa">
                    @else
                        <div style="font-size: 18px; font-weight: bold; color: #0f172a;">{{ $company_name }}</div>
                    @endif
                </td>
                <td class="header-company">
                    <div class="company-title">{{ $company_name }}</div>
                    <div class="company-info">
                        <strong>R.U.C.:</strong> {{ $company_ruc }}<br>
                        <strong>Dirección:</strong> {{ $company_address }}<br>
                        @if ($company_phone)
                            <strong>Teléfono:</strong> {{ $company_phone }} &nbsp;|&nbsp;
                        @endif
                        @if ($company_email)
                            <strong>Email:</strong> {{ $company_email }}
                        @endif
                    </div>
                </td>
                <td class="header-doc">
                    <div class="doc-badge">Rol de Pagos</div>
                    <div class="doc-number"># {{ $doc_number }}</div>
                    <div class="doc-period"><strong>Período:</strong> {{ $month_label }}</div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Fecha Emisión: {{ $payment_date }}</div>
                </td>
            </tr>
        </table>

        <!-- Datos del Empleado -->
        <div class="section-title">Datos Generales del Empleado</div>
        <table class="info-table">
            <tr>
                <td class="info-label">Empleado:</td>
                <td class="info-val"><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                <td class="info-label">Cédula / Identificación:</td>
                <td class="info-val"><strong>{{ $employee->identification ?? 'N/A' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Cargo / Puesto:</td>
                <td class="info-val">{{ $employee->position ?? 'No especificado' }}</td>
                <td class="info-label">Fecha de Ingreso:</td>
                <td class="info-val">{{ $employee->hired_at ? \Carbon\Carbon::parse($employee->hired_at)->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-label">Teléfono:</td>
                <td class="info-val">{{ $employee->phone ?? 'N/A' }}</td>
                <td class="info-label">Correo Electrónico:</td>
                <td class="info-val">{{ $employee->email ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-label">Método de Pago:</td>
                <td class="info-val"><strong>{{ $payment->payment_method ?? 'TRANSFERENCIA' }}</strong></td>
                <td class="info-label">Cuenta Bancaria / Caja:</td>
                <td class="info-val">{{ $account_name }}</td>
            </tr>
            @if ($payment->reference)
                <tr>
                    <td class="info-label">Nº Referencia / Depósito:</td>
                    <td class="info-val" colspan="3">{{ $payment->reference }}</td>
                </tr>
            @endif
        </table>

        <!-- Desglose de Ingresos y Egresos -->
        <table class="breakdown-table">
            <tr>
                <!-- Columna Ingresos -->
                <td class="col-ingresos">
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th class="th-ingresos" colspan="2">1. Ingresos y Haberes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Sueldo Básico Pactado</td>
                                <td class="text-right font-weight-bold">${{ number_format($base_salary, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Horas Extras / Bonificaciones</td>
                                <td class="text-right">$0.00</td>
                            </tr>
                            <tr>
                                <td>Comisiones / Otros Haberes</td>
                                <td class="text-right">$0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td style="color: #15803d;">TOTAL INGRESOS (A)</td>
                                <td class="text-right" style="color: #15803d;">${{ number_format($base_salary, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>

                <td class="col-spacer"></td>

                <!-- Columna Descuentos / Adelantos -->
                <td class="col-egresos">
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th class="th-egresos" colspan="2">2. Deducciones y Adelantos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($advances && count($advances) > 0)
                                @foreach ($advances as $adv)
                                    <tr>
                                        <td>
                                            <strong>Adelanto ({{ \Carbon\Carbon::parse($adv->advance_date)->format('d/m/Y') }}):</strong><br>
                                            <span style="color: #64748b; font-size: 8.5px;">{{ $adv->reason ?: ($adv->description ?: 'Adelanto de sueldo') }}</span>
                                        </td>
                                        <td class="text-right" style="color: #dc2626; vertical-align: top;">-${{ number_format($adv->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="2" style="color: #64748b; text-align: center; padding: 12px 6px;">
                                        <em>No registra adelantos ni descuentos en este mes.</em>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td style="color: #b91c1c;">TOTAL DEDUCCIONES (B)</td>
                                <td class="text-right" style="color: #b91c1c;">-${{ number_format($advances_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Resumen Neto a Recibir -->
        <table class="net-banner">
            <tr>
                <td>
                    <div class="net-title">Líquido a Recibir / Neto Pagado (A - B):</div>
                    <div class="net-letters">
                        {{ $amount_in_words ?? '' }}
                    </div>
                </td>
                <td class="net-amount">
                    ${{ number_format($net_amount, 2) }}
                </td>
            </tr>
        </table>

        @if ($payment->description)
            <div style="font-size: 9.5px; color: #475569; margin-bottom: 8px;">
                <strong>Observaciones / Detalle:</strong> {{ $payment->description }}
            </div>
        @endif

        <!-- Texto Legal de Conformidad -->
        <div class="conformity-text">
            <strong>DECLARACIÓN DE CONFORMIDAD:</strong> Certifico haber recibido a mi entera satisfacción de parte de <strong>{{ $company_name }}</strong> el valor neto especificado en el presente Rol Individual de Pagos correspondiente al período de <strong>{{ $month_label }}</strong>, por concepto de liquidación mensual de mis haberes laborales, dejando expresa constancia de que no se me adeuda valor alguno por este período.
        </div>

        <!-- Sección de Firmas y Sello de la Compañía -->
        <table class="signatures-table">
            <tr>
                <!-- Firma del Empleado -->
                <td>
                    <div class="signature-line"></div>
                    <div class="sig-name">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                    <div class="sig-detail"><strong>C.I.:</strong> {{ $employee->identification ?? '____________________' }}</div>
                    <div class="sig-detail">Firma del Empleado / Beneficiario</div>
                    <div class="sig-detail" style="font-size: 8px; margin-top: 3px;">Fecha Recibido: {{ $payment_date }}</div>
                </td>

                <td class="sig-spacer"></td>

                <!-- Firma y Sello de la Compañía -->
                <td>
                    <!-- Sello de la Compañía -->
                    <div class="company-stamp">
                        <div class="stamp-company">{{ $company_name }}</div>
                        <div class="stamp-status">PAGADO</div>
                        <div class="stamp-ruc">RUC: {{ $company_ruc }}</div>
                        <div class="stamp-date">{{ $payment_date }} - {{ $account_name }}</div>
                    </div>
                    <div class="signature-line" style="margin-top: 5px;"></div>
                    <div class="sig-name">{{ $company_name }}</div>
                    <div class="sig-detail">Firma Autorizada / Departamento de Nómina</div>
                    <div class="sig-detail">R.U.C. {{ $company_ruc }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>

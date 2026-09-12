<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>RIDE - Factura {{ $sale->document_number }}</title>
    <style>
        * {
            font-family: Helvetica, Arial, sans-serif !important;
            box-sizing: border-box;
        }

        @page {
            margin: 12mm 14mm 10mm 14mm;
            size: letter portrait;
        }

        body,
        table,
        th,
        td,
        tr,
        thead,
        tbody,
        div,
        span,
        p,
        strong,
        b,
        a,
        input,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: Helvetica, Arial, sans-serif !important;
        }

        body {
            font-family: Helvetica, Arial, sans-serif !important;
            font-size: 8.8px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.25;
            padding: 0;
            margin: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── HEADER TWO-COLUMN BOXES (EQUAL DIMENSIONS & PERFECT ALIGNMENT) ───────── */
        .layout-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .layout-table td {
            padding: 0;
            margin: 0;
        }

        .card-panel {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            padding: 4px 8px;
            box-sizing: border-box;
            height: 128px;
        }

        .card-panel-shaded {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            /* background: #f8fafc; */
            padding: 6px 8px;
            box-sizing: border-box;
            height: 206px;
        }

        .logo-box {
            text-align: left;
            margin-bottom: 4px;
            height: 78px;
        }

        .logo-img {
            max-height: 76px;
            max-width: 235px;
            object-fit: contain;
            display: block;
            margin: 0 0 2px 0;
        }

        .company-title {
            font-size: 14.5px;
            font-weight: 550;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .company-subtitle {
            font-size: 10px;
            color: #43536bff;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .company-detail {
            font-size: 9.2px;
            color: #334155;
            margin-bottom: 2px;
            line-height: 1.35;
        }

        .company-detail strong {
            color: #0f172a;
        }

        /* Right column headers */
        .doc-ruc {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.4px;
            border-bottom: 0.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 3px;
        }

        .doc-type-badge {
            display: inline-block;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            letter-spacing: 1.2px;
            margin-top: 3px;
            margin-bottom: 2px;
        }

        .doc-number {
            font-size: 10.5px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .auth-label {
            font-size: 8.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-top: 2px;
            margin-bottom: 1px;
            font-family: Helvetica, Arial, sans-serif !important;
        }

        .auth-value {
            font-family: Helvetica, Arial, sans-serif !important;
            font-size: 9px;
            color: #0f172a;
            word-break: break-all;
            margin-bottom: 2px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .meta-grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        .meta-grid td {
            font-size: 8.8px;
            padding: 1px 0;
            color: #1e293b;
            font-family: Helvetica, Arial, sans-serif !important;
        }

        .meta-grid strong {
            color: #475569;
        }

        .barcode-container {
            text-align: center;
            margin-top: 10px;
            padding: 2px 3px 1px 3px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }

        .access-key-text {
            font-family: Helvetica, Arial, sans-serif !important;
            font-size: 8.2px;
            letter-spacing: 0.3px;
            color: #0f172a;
            margin-top: 1px;
            text-align: center;
            font-weight: 700;
            word-break: break-all;
            line-height: 1.1;
        }

        /* ── CARD BOXES (CLIENT & DETAILS) ─────────────────────── */
        .card-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            margin-bottom: 7px;
            overflow: hidden;
            background: #ffffff;
        }

        .card-header {
            background: #e2e8f0;
            color: #0f172a;
            padding: 4px 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #cbd5e1;
        }

        .card-body {
            padding: 6px 10px;
        }

        .client-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .client-table td {
            padding: 2.2px 3px;
            font-size: 8.8px;
            vertical-align: top;
            line-height: 1.25;
        }

        .c-label {
            font-weight: 700;
            color: #475569;
            width: 38%;
        }

        .c-val {
            color: #0f172a;
            width: 62%;
            word-wrap: break-word;
        }

        /* ── DETAILS TABLE ─────────────────────────────────────── */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
        }

        .items-table thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 5px 6px;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #cbd5e1;
            border-top: none;
            border-left: none;
            border-right: none;
        }

        .items-table tbody td {
            padding: 4.5px 6px;
            font-size: 8.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            border-left: none;
            border-right: none;
            vertical-align: middle;
            color: #1e293b;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        /* ── BOTTOM SECTION (INFO ADICIONAL + TOTALES) ─────────── */
        .info-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2.5px 3px;
            font-size: 8.5px;
        }

        .totals-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
        }

        .totals-table td {
            padding: 3px 8px;
            font-size: 8.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .tot-label {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            width: 60%;
        }

        .tot-val {
            text-align: right;
            font-weight: 700;
            color: #0f172a;
            font-size: 8.8px;
            width: 40%;
        }

        .tot-highlight {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-top: 1px solid #cbd5e1 !important;
        }

        .tot-highlight td {
            padding: 5px 8px;
            font-size: 10.5px;
            font-weight: 800;
            font-family: Helvetica, Arial, sans-serif !important;
            color: #0f172a !important;
        }

        .tot-highlight .tot-label {
            font-weight: 800;
            font-family: Helvetica, Arial, sans-serif !important;
            color: #0f172a !important;
        }

        .tot-highlight .tot-val {
            font-weight: 800;
            font-family: Helvetica, Arial, sans-serif !important;
            color: #0f172a !important;
        }

        /* ── FOOTER ─────────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -36px;
            left: 0;
            right: 0;
            height: 32px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 7px;
            color: #64748b;
            line-height: 1.3;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>

<body>
    @php
    // Cargar logo en base64 para renderizado perfecto
    $logoBase64 = '';
    $logoCandidates = [
    $sucursal->logo ? storage_path('app/public/' . str_replace('storage/', '', ltrim($sucursal->logo, '/'))) : null,
    $sucursal->logo ? public_path($sucursal->logo) : null,
    public_path('assets/img/brand/logo.png'),
    public_path('assets/img/brand/logo.jpeg'),
    public_path('logo.png'),
    ];
    foreach ($logoCandidates as $cand) {
    if ($cand && file_exists($cand) && filesize($cand) > 0) {
    $ext = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['png', 'gif', 'svg']) ? "image/{$ext}" : 'image/jpeg';
    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($cand));
    break;
    }
    }

    $estab = str_pad($sucursal->establishment_code ?? '001', 3, '0', STR_PAD_LEFT);
    $ptoEmi = str_pad($sucursal->emission_point_code ?? '001', 3, '0', STR_PAD_LEFT);
    $secuencial = str_pad(preg_replace('/\D/', '', $sale->document_number), 9, '0', STR_PAD_LEFT);
    $numeroFormateado = "{$estab}-{$ptoEmi}-{$secuencial}";

    $fechaEmision = $sale->service_date
    ? \Carbon\Carbon::parse($sale->service_date)->format('d/m/Y')
    : now()->format('d/m/Y');

    $isAutorizada = in_array(strtoupper($sale->sri_status ?? ''), ['AUTORIZADA', 'AUTORIZADO']);
    $fechaAutorizacion = $autorizacion['fechaAutorizacion']
    ?? ($sale->sri_authorization_date ? \Carbon\Carbon::parse($sale->sri_authorization_date)->format('d/m/Y H:i:s') : null);
    $numeroAutorizacion = $autorizacion['numeroAutorizacion'] ?? $sale->sri_access_key;

    // Resolución de Datos del Vehículo
    $vehicle = $sale->vehicle ?: ($sale->workOrder ? $sale->workOrder->vehicle : null);
    $vehicleBrands = config('vehicle_brands', []);
    $brandRaw = $vehicle->brand ?? '';
    $brandName = '';
    if (!empty($brandRaw)) {
    $brandName = is_numeric($brandRaw) ? ($vehicleBrands[(int)$brandRaw] ?? $brandRaw) : $brandRaw;
    $brandName = ucwords(strtolower((string)$brandName));
    }
    $vehicleModel = $vehicle->model ?? '';
    $vehicleFull = trim("{$brandName} {$vehicleModel}");
    $vehicleYear = $vehicle->year ?? null;
    $vehicleTypes = config('vehicle_types', []);
    $typeRaw = $vehicle->vehicle_type ?? null;
    $vehicleType = !empty($typeRaw) ? (is_numeric($typeRaw) ? ($vehicleTypes[(int)$typeRaw] ?? $typeRaw) : $typeRaw) : null;
    $vehicleType = $vehicleType ? ucwords(strtolower((string)$vehicleType)) : null;
    $mileageVal = $sale->mileage ?: ($sale->workOrder ? $sale->workOrder->mileage : null);
    $hasVehicle = !empty($vehicle) && (!empty($vehicle->license_plate) || !empty($vehicleFull));
    @endphp

    <div class="container">

        {{-- ═══ CABECERA SRI (2 COLUMNAS OFICIALES DE IGUAL DIMENSIÓN) ════════════════════════ --}}
        <table class="layout-table">
            <tr>
                {{-- COLUMNA IZQUIERDA: DATOS DE LA EMPRESA --}}
                <td style="width: 49.4%; vertical-align: top;">
                    <div class="logo-box">
                        @if (!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
                        @endif
                    </div>
                    <div class="card-panel">
                        <table style="width: 100%; height: 120px; border-collapse: collapse; border: none;">
                            <tr>
                                <td style="vertical-align: middle; padding: 6px; border: none; margin-top: 10px;">
                                    <div class="company-title">{{ $sucursal->trade_name ?? $sucursal->name ?? 'LUXURY EVYS' }}</div>
                                    @if (!empty($sucursal->trade_name) && $sucursal->trade_name !== $sucursal->name)
                                    <div class="company-subtitle">{{ $sucursal->name }}</div>
                                    @endif

                                    <div class="company-detail" style="margin-top: 2px;">
                                        <strong>Dirección Matriz:</strong> {{ $sucursal->address ?? 'SUR DE QUITO' }}
                                    </div>
                                    @if (!empty($sucursal->branch_address) && $sucursal->branch_address !== $sucursal->address)
                                    <div class="company-detail">
                                        <strong>Dirección Sucursal:</strong> {{ $sucursal->branch_address }}
                                    </div>
                                    @endif
                                    <div class="company-detail">
                                        <strong>Teléfono:</strong> {{ $sucursal->phone ?? '0999179988' }}
                                    </div>
                                    <div class="company-detail">
                                        <strong>Email:</strong> {{ $sucursal->email ?? 'comp.luxuryevys@gmail.com' }}
                                    </div>
                                    <div class="company-detail" style="margin-top: 1px;">
                                        <strong>Obligado a Llevar Contabilidad:</strong>
                                        <span style="font-weight: 700; color: #334155;">{{ strtoupper($sucursal->obligado_contabilidad ?? 'SI') }}</span>
                                    </div>
                                    @if (!empty($sucursal->contribuyente_especial))
                                    <div class="company-detail">
                                        <strong>Contribuyente Especial Nro:</strong> {{ $sucursal->contribuyente_especial }}
                                    </div>
                                    @endif
                                    @if (!empty($sucursal->regimen_rimpe))
                                    <div class="company-detail" style="color: #334155; font-weight: 700;">
                                        CONTRIBUYENTE RÉGIMEN RIMPE
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>

                {{-- ESPACIADOR CENTRAL --}}
                <td style="width: 1.2%;"></td>

                {{-- COLUMNA DERECHA: DATOS TRIBUTARIOS Y FISCALES --}}
                <td style="width: 49.4%; vertical-align: top;">
                    <div class="card-panel-shaded">
                        <div class="doc-ruc">R.U.C.: {{ $sucursal->ruc ?? '1793192550001' }}</div>
                        <div class="doc-type-badge">FACTURA</div>
                        <div class="doc-number">No. {{ $numeroFormateado }}</div>

                        <div class="auth-label">NÚMERO DE AUTORIZACIÓN:</div>
                        <div class="auth-value">{{ $numeroAutorizacion ?: 'PENDIENTE' }}</div>

                        <table class="meta-grid">
                            <tr>
                                <td style="width: 50%;">
                                    <strong>FECHA Y HORA DE AUTORIZACIÓN:</strong>
                                </td>
                                <td style="width: 50%; font-weight: 700;">
                                    {{ $fechaAutorizacion ?: ($isAutorizada ? $fechaEmision : 'PENDIENTE') }}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>AMBIENTE:</strong></td>
                                @php
                                $ambVal = (int) ($sucursal->ambiente ?? env('SRI_AMBIENTE', 1));
                                @endphp
                                <td style="font-weight: 700; color: {{ $ambVal === 1 ? '#d97706' : '#16a34a' }};">
                                    {{ $ambVal === 1 ? 'PRUEBAS' : 'PRODUCCIÓN' }}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>EMISIÓN:</strong></td>
                                <td style="font-weight: 700;">NORMAL</td>
                            </tr>
                        </table>

                        <div class="barcode-container">
                            <div class="auth-label" style="margin-top: 0; margin-bottom: 1px;">CLAVE DE ACCESO</div>
                            @if (!empty($sale->sri_access_key))
                            <div style="padding: 1px 0;">
                                {!! \App\Helpers\PdfHelper::generateBarcodeHTML($sale->sri_access_key, 25) !!}
                            </div>
                            <div class="access-key-text">{{ $sale->sri_access_key }}</div>
                            @else
                            <div style="color: #94a3b8; font-size: 7px;">SIN CLAVE DE ACCESO</div>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- ═══ DATOS DEL RECEPTOR / CLIENTE Y VEHÍCULO ════════════════════════ --}}
        <div class="card-box">
            @if ($hasVehicle)
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                <tr>
                    <td class="card-header" style="width: 50%; text-align: left; padding: 4px 10px;">Información del Comprador</td>
                    <td class="card-header" style="width: 50%; text-align: left; padding: 4px 10px;">Información del Vehículo</td>
                </tr>
            </table>
            <div class="card-body" style="padding: 6px 10px;">
                <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                    <tr>
                        {{-- COLUMNA 1: INFORMACIÓN DEL CLIENTE --}}
                        <td style="width: 48.5%; vertical-align: top; padding-right: 6px;">
                            <table class="client-table">
                                <tr>
                                    <td class="c-label">Razón Social:</td>
                                    <td class="c-val" style="font-weight: 700;">{{ $sale->client->full_name ?? $sale->client->name ?? 'CONSUMIDOR FINAL' }}</td>
                                </tr>
                                <tr>
                                    <td class="c-label">Identificación:</td>
                                    <td class="c-val" style="font-weight: 700;">{{ $sale->client->n_document ?? '9999999999999' }}</td>
                                </tr>
                                <tr>
                                    <td class="c-label">Tipo Identificación:</td>
                                    <td class="c-val">
                                        @php
                                        $typeDoc = strtolower(trim((string)($sale->client->type_document ?? '')));
                                        $docNum = trim((string)($sale->client->n_document ?? ''));
                                        $tipoDocNombre = match (true) {
                                        $docNum === '9999999999999' || $typeDoc === '4' || $typeDoc === '07' => 'Consumidor Final',
                                        $typeDoc === '2' || $typeDoc === '04' || $typeDoc === 'ruc' || strlen($docNum) === 13 => 'RUC',
                                        $typeDoc === '3' || $typeDoc === '06' || $typeDoc === 'pasaporte' => 'Pasaporte',
                                        default => 'Cédula de Identidad',
                                        };
                                        @endphp
                                        {{ $tipoDocNombre }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="c-label">Fecha Emisión:</td>
                                    <td class="c-val">{{ $fechaEmision }}</td>
                                </tr>
                                <tr>
                                    <td class="c-label">Dirección:</td>
                                    <td class="c-val">{{ $sale->client->address ?? 'SUR DE QUITO' }}</td>
                                </tr>
                            </table>
                        </td>

                        {{-- ESPACIADOR CENTRAL SIN LÍNEA --}}
                        <td style="width: 3%;"></td>

                        {{-- COLUMNA 2: INFORMACIÓN DEL VEHÍCULO --}}
                        <td style="width: 48.5%; vertical-align: top; padding-left: 6px;">
                            <table class="client-table">
                                <tr>
                                    <td class="c-label">Placa:</td>
                                    <td class="c-val" style="font-weight: 700; color: #0f172a;">
                                        {{ $vehicle->license_plate ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="c-label">Vehículo:</td>
                                    <td class="c-val" style="text-transform: uppercase;">
                                        {{ $vehicleFull ?: 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="c-label">Año:</td>
                                    <td class="c-val">{{ $vehicleYear ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="c-label">Tipo:</td>
                                    <td class="c-val">{{ $vehicleType ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="c-label">Kilometraje:</td>
                                    <td class="c-val">{{ !empty($mileageVal) ? number_format($mileageVal) . ' km' : 'N/A' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
            @else
            <div class="card-header">Información del Comprador</div>
            <div class="card-body" style="padding: 6px 10px;">
                <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                    <tr>
                        <td style="width: 20%; font-weight: 700; color: #475569; padding: 2.2px 3px; font-size: 8.8px;">Razón Social / Nombres:</td>
                        <td style="width: 32%; color: #0f172a; font-weight: 700; padding: 2.2px 3px; font-size: 8.8px;">{{ $sale->client->full_name ?? $sale->client->name ?? 'CONSUMIDOR FINAL' }}</td>
                        <td style="width: 18%; font-weight: 700; color: #475569; padding: 2.2px 3px; font-size: 8.8px;">Identificación:</td>
                        <td style="width: 30%; color: #0f172a; font-weight: 700; padding: 2.2px 3px; font-size: 8.8px;">{{ $sale->client->n_document ?? '9999999999999' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700; color: #475569; padding: 2.2px 3px; font-size: 8.8px;">Fecha Emisión:</td>
                        <td style="color: #0f172a; padding: 2.2px 3px; font-size: 8.8px;">{{ $fechaEmision }}</td>
                        <td style="font-weight: 700; color: #475569; padding: 2.2px 3px; font-size: 8.8px;">Tipo Identificación:</td>
                        <td style="color: #0f172a; padding: 2.2px 3px; font-size: 8.8px;">
                            @php
                            $typeDoc = strtolower(trim((string)($sale->client->type_document ?? '')));
                            $docNum = trim((string)($sale->client->n_document ?? ''));
                            $tipoDocNombre = match (true) {
                            $docNum === '9999999999999' || $typeDoc === '4' || $typeDoc === '07' => 'Consumidor Final',
                            $typeDoc === '2' || $typeDoc === '04' || $typeDoc === 'ruc' || strlen($docNum) === 13 => 'RUC',
                            $typeDoc === '3' || $typeDoc === '06' || $typeDoc === 'pasaporte' => 'Pasaporte',
                            default => 'Cédula de Identidad',
                            };
                            @endphp
                            {{ $tipoDocNombre }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700; color: #475569; padding: 2.2px 3px; font-size: 8.8px;">Dirección:</td>
                        <td style="color: #0f172a; padding: 2.2px 3px; font-size: 8.8px;" colspan="3">{{ $sale->client->address ?? 'SUR DE QUITO' }}</td>
                    </tr>
                </table>
            </div>
            @endif
        </div>

        {{-- ═══ TABLA DE DETALLES (PRODUCTOS / SERVICIOS) ════════════════════ --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%; text-align: left; padding-left: 8px;">Cod. Principal</th>
                    <th style="width: 8%; text-align: center;">Cant.</th>
                    <th style="width: 42%; text-align: left;">Descripción</th>
                    <th style="width: 12%; text-align: right;">Precio Unit.</th>
                    <th style="width: 10%; text-align: right;">Descuento</th>
                    <th style="width: 13%; text-align: right; padding-right: 8px;">Precio Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->details as $index => $detalle)
                @php
                $taxRate = (float)($detalle->tax_rate ?? 15.00);
                $qty = (float)($detalle->quantity ?? 1);
                $grossPvp = $qty * (float)$detalle->price;
                $discount = (float)($detalle->discount ?? 0);
                $itemTotal = max(0, $grossPvp - $discount);

                if ($taxRate > 0) {
                $unitSinImpuesto = round((float)$detalle->price / (1 + ($taxRate / 100)), 4);
                $subtotalItem = round($itemTotal / (1 + ($taxRate / 100)), 2);
                } else {
                $unitSinImpuesto = (float)$detalle->price;
                $subtotalItem = $itemTotal;
                }
                $codPrincipal = !empty($detalle->product?->sku)
                ? $detalle->product->sku
                : ($detalle->product_id ? str_pad($detalle->product_id, 6, '0', STR_PAD_LEFT) : str_pad($index + 1, 6, '0', STR_PAD_LEFT));
                @endphp
                <tr>
                    <td class="text-left" style="font-weight: 600; padding-left: 8px;">{{ $codPrincipal }}</td>
                    <td class="text-center">{{ number_format($qty, 2) }}</td>
                    <td class="text-left" style="font-size: 8.5px;">{{ $detalle->description }}</td>
                    <td class="text-right">${{ number_format($unitSinImpuesto, 4) }}</td>
                    <td class="text-right">${{ number_format(($discount/1.15), 2) }}</td>
                    <td class="text-right" style="font-weight: 700; padding-right: 8px;">${{ number_format($subtotalItem, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{-- ═══ SECCIÓN INFERIOR: INFORMACIÓN ADICIONAL Y TOTALES (2 COLUMNAS DE IGUAL DIMENSIÓN) ═══════════ --}}
        <table class="layout-table" style="margin-bottom: 4px;">
            <tr>
                {{-- INFORMACIÓN ADICIONAL & FORMAS DE PAGO --}}
                <td style="width: 49.25%; vertical-align: top;">
                    <div class="card-box" style="margin-bottom: 6px;">
                        <div class="card-header">Información Adicional</div>
                        <div class="card-body">
                            <table class="info-table">
                                @php
                                $otNumber = $sale->work_order_number ?: ($sale->workOrder->number ?? null);
                                @endphp
                                @if (!empty($otNumber))
                                <tr>
                                    <td style="font-weight: 700; width: 34%; color: #475569;">Orden de Trabajo:</td>
                                    <td style="color: #0f172a; font-weight: 700;">{{ $otNumber }}</td>
                                </tr>
                                @endif

                                <tr>
                                    <td style="font-weight: 700; width: 34%; color: #475569;">Email:</td>
                                    <td style="color: #0f172a;">{{ $sale->client->email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 700; color: #475569;">Teléfono:</td>
                                    <td style="color: #0f172a;">{{ $sale->client->phone ?? $sale->client->cellphone ?? 'N/A' }}</td>
                                </tr>
                                @if (!empty($sale->observations))
                                <tr>
                                    <td style="font-weight: 700; color: #475569;">Observaciones:</td>
                                    <td style="color: #0f172a;">{{ $sale->observations }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <div class="card-box" style="margin-bottom: 0;">
                        <div class="card-header">Formas de Pago</div>
                        <div class="card-body" style="padding: 4px 8px;">
                            <table class="info-table">
                                <thead>
                                    <tr style="border-bottom: 1px solid #cbd5e1;">
                                        <th style="text-align: left; font-size: 7.8px; padding-bottom: 2px; color: #0f172a; font-weight: 700; width: 48%;">Forma de Pago</th>
                                        <th style="text-align: right; font-size: 7.8px; padding-bottom: 2px; color: #0f172a; font-weight: 700; width: 24%;">Total</th>
                                        <th style="text-align: center; font-size: 7.8px; padding-bottom: 2px; color: #0f172a; font-weight: 700; width: 14%;">Plazo</th>
                                        <th style="text-align: center; font-size: 7.8px; padding-bottom: 2px; color: #0f172a; font-weight: 700; width: 14%;">Tiempo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $mapSriFormaPago = function($method, $account = null) {
                                    $m = strtolower(trim((string)$method));
                                    $accType = strtolower(trim((string)($account?->type ?? '')));
                                    $accName = strtolower(trim((string)($account?->name ?? '')));

                                    // Chequeos directos por código oficial SRI o términos
                                    if ($m === '01' || $m === 'cash' || $m === 'efectivo' || str_contains($m, 'efectivo') || str_contains($m, 'sin utilizacion') || str_contains($m, 'sin utilización')) {
                                    return 'SIN UTILIZACION DEL SISTEMA FINANCIERO';
                                    }
                                    if ($m === '16' || str_contains($m, 'debito') || str_contains($m, 'débito')) {
                                    return 'TARJETA DE DEBITO';
                                    }
                                    if ($m === '17' || str_contains($m, 'dinero electronico') || str_contains($m, 'dinero electrónico')) {
                                    return 'DINERO ELECTRONICO';
                                    }
                                    if ($m === '18' || str_contains($m, 'prepago')) {
                                    return 'TARJETA PREPAGO';
                                    }
                                    if ($m === '19' || (str_contains($m, 'credito') && str_contains($m, 'tarjeta')) || (str_contains($m, 'crédito') && str_contains($m, 'tarjeta')) || str_contains($m, 'card') || $m === 'tarjeta') {
                                    return 'TARJETA DE CREDITO';
                                    }
                                    if ($m === '15' || str_contains($m, 'compensacion') || str_contains($m, 'compensación')) {
                                    return 'COMPENSACION DE DEUDAS';
                                    }
                                    if ($m === '21' || str_contains($m, 'endoso')) {
                                    return 'ENDOSO DE TITULOS';
                                    }
                                    if (
                                    $m === '20' ||
                                    $m === 'transfer' ||
                                    $m === 'transferencia' ||
                                    str_contains($m, 'transfer') ||
                                    str_contains($m, 'deposito') ||
                                    str_contains($m, 'depósito') ||
                                    str_contains($m, 'cheque') ||
                                    str_contains($m, 'banco') ||
                                    str_contains($m, 'con utilizacion') ||
                                    str_contains($m, 'con utilización') ||
                                    str_contains($m, 'credito') ||
                                    str_contains($m, 'crédito') ||
                                    $accType === 'bank' ||
                                    str_contains($accName, 'banco') ||
                                    str_contains($accName, 'transfer')
                                    ) {
                                    return 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO';
                                    }

                                    if ($accType === 'bank') {
                                    return 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO';
                                    }

                                    return 'SIN UTILIZACION DEL SISTEMA FINANCIERO';
                                    };

                                    $formasPago = [];
                                    if ($sale->financeRecord && $sale->financeRecord->paymentDistributions && $sale->financeRecord->paymentDistributions->count() > 0) {
                                    foreach ($sale->financeRecord->paymentDistributions as $pd) {
                                    $formasPago[] = [
                                    'descripcion' => $mapSriFormaPago($pd->payment_method ?? ($pd->account->name ?? 'cash'), $pd->account ?? null),
                                    'total' => (float)$pd->amount,
                                    'plazo' => '0',
                                    'tiempo' => 'días',
                                    ];
                                    }
                                    }

                                    if (empty($formasPago)) {
                                    $formasPago[] = [
                                    'descripcion' => $mapSriFormaPago($sale->payment_method ?? 'cash'),
                                    'total' => (float)$sale->total,
                                    'plazo' => '0',
                                    'tiempo' => 'días',
                                    ];
                                    }
                                    @endphp

                                    @foreach ($formasPago as $fp)
                                    <tr>
                                        <td style="font-family: Helvetica, Arial, sans-serif !important; font-size: 8px;  color: #1e293b; text-transform: uppercase;">{{ $fp['descripcion'] }}</td>
                                        <td style="font-family: Helvetica, Arial, sans-serif !important; text-align: right; font-size: 8.5px; color: #0f172a;">${{ number_format($fp['total'], 2) }}</td>
                                        <td style="font-family: Helvetica, Arial, sans-serif !important; text-align: center; font-size: 8px; color: #475569;">{{ $fp['plazo'] }}</td>
                                        <td style="font-family: Helvetica, Arial, sans-serif !important; text-align: center; font-size: 8px; color: #475569;">{{ $fp['tiempo'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>

                {{-- ESPACIADOR CENTRAL --}}
                <td style="width: 1.5%;"></td>

                {{-- TOTALES OFICIALES SRI --}}
                <td style="width: 49.25%; vertical-align: top;">
                    @php
                    $subtotalSinImp = (float)$sale->subtotal;
                    $descuentoTotal = (float)$sale->details->sum('discount');
                    $iva15 = (float)($sale->total - $sale->subtotal);
                    $importeTotal = (float)$sale->total;
                    @endphp
                    <table class="totals-table">
                        <tr>
                            <td class="tot-label">Subtotal 15%</td>
                            <td class="tot-val">${{ number_format($subtotalSinImp, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Subtotal 0%</td>
                            <td class="tot-val">$0.00</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Subtotal No Objeto de IVA</td>
                            <td class="tot-val">$0.00</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Subtotal Exento de IVA</td>
                            <td class="tot-val">$0.00</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Subtotal Sin Impuestos</td>
                            <td class="tot-val">${{ number_format($subtotalSinImp, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Total Descuento</td>
                            <td class="tot-val">${{ number_format(($descuentoTotal/1.15), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="tot-label">IVA 15%</td>
                            <td class="tot-val">${{ number_format($iva15, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="tot-label">Propina</td>
                            <td class="tot-val">$0.00</td>
                        </tr>
                        <tr class="tot-highlight">
                            <td class="tot-label">VALOR TOTAL</td>
                            <td class="tot-val">${{ number_format($importeTotal, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ═══ PIE DE PÁGINA FIJO ═══════════════════════════════════════════ --}}
        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td style="width: 80%; text-align: left; vertical-align: middle; color: #64748b; font-size: 6.5px;">
                        Este documento es una representación impresa de un Comprobante Electrónico (RIDE) de acuerdo al Art. 21 del Reglamento de Comprobantes de Venta y Retención.<br>
                        Para consultar la validez del comprobante, ingrese a <strong>https://sri.gob.ec</strong>.
                    </td>
                    <td style="width: 20%; text-align: right; vertical-align: middle;">
                        {{-- El número de página dinámico "Página X de Y" es inyectado por el motor PHP de DomPDF --}}
                    </td>
                </tr>
            </table>
        </div>

    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Helvetica", "bold");
            $size = 6.8;
            $color = [0.28, 0.33, 0.41]; // #475569
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $pdf->page_text(510, 818, $text, $font, $size, $color);
        }
    </script>

    @if(request()->has('print'))
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
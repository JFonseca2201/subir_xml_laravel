<!doctype html>
<html class="no-js" lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ $sale->document_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no">

    <style>
        @page {
            size: letter portrait;
            margin: 8mm 15mm 18mm 15mm;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
        }

        a {
            color: inherit !important;
            text-decoration: none !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table tr td {
            padding: 0;
        }

        .bold {
            font-weight: bold;
        }

        .semibold {
            font-weight: 600;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .valign-top {
            vertical-align: top;
        }

        /* ── CABECERA SRI OFICIAL ── */
        .sri-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .sri-header-left {
            width: 48%;
            vertical-align: top;
            padding-right: 8px;
            text-align: left;
        }

        .sri-header-spacer {
            width: 2.5%;
        }

        .sri-header-right {
            width: 49.5%;
            vertical-align: top;
            padding-left: 0;
            text-align: left;
        }

        .logo-box {
            text-align: left;
            margin-bottom: 10px;
            height: 86px;
        }

        .logo-img {
            max-height: 82px;
            max-width: 250px;
            object-fit: contain;
            display: block;
        }

        .sri-box {
            border: 1.2px solid #111111;
            border-radius: 8px;
            padding: 10px 14px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.35;
            margin-bottom: 8px;
            text-align: left;
        }

        .company-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            line-height: 1.35;
            margin-bottom: 6px;
        }

        .comp-label {
            width: 33%;
            font-weight: bold;
            vertical-align: top;
            color: #1e293b;
            padding: 1.5px 0;
            text-align: left;
        }

        .comp-val {
            width: 67%;
            vertical-align: top;
            color: #334155;
            padding: 1.5px 0;
            text-align: left;
        }

        .company-tax-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-top: 4px;
        }

        .tax-label {
            font-weight: bold;
            color: #1e293b;
            padding: 1.5px 0;
            text-align: left;
        }

        .tax-val {
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            padding: 1.5px 0;
        }

        .rimpe-label {
            font-weight: bold;
            color: #0f172a;
            padding: 2px 0 0 0;
            text-align: left;
        }

        /* ── DERECHA: DATOS TRIBUTARIOS ── */
        .fiscal-ruc {
            font-size: 17.5px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
            margin-bottom: 6px;
            text-align: left;
        }

        .fiscal-doc-type {
            font-size: 14.5px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            text-align: left;
        }

        .fiscal-doc-number {
            font-size: 9.5px;
            color: #1e293b;
            margin-bottom: 7px;
            text-align: left;
        }

        .doc-num-label {
            font-weight: bold;
        }

        .doc-num-value {
            font-weight: bold;
            font-size: 11px;
            color: #0f172a;
            letter-spacing: 0.3px;
        }

        .auth-label {
            font-size: 8.5px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 2px;
            text-align: left;
        }

        .auth-number {
            font-size: 8.5px;
            font-family: 'Courier New', Courier, monospace;
            color: #0f172a;
            word-break: break-all;
            margin-bottom: 7px;
            letter-spacing: 0.2px;
            text-align: left;
            line-height: 1.2;
        }

        .fiscal-meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.8px;
            margin-bottom: 7px;
        }

        .meta-label {
            font-weight: bold;
            color: #1e293b;
            padding: 1.5px 0;
            text-align: left;
            width: 50%;
        }

        .meta-val {
            color: #0f172a;
            padding: 1.5px 0;
            text-align: left;
            width: 50%;
        }

        .access-key-container {
            margin-top: 4px;
            text-align: left;
        }

        .access-key-label {
            font-size: 8.5px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 3px;
            text-align: left;
        }

        .barcode-box {
            text-align: center;
            margin: 2px 0;
        }

        .access-key-digits {
            text-align: center;
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.2px;
            color: #0f172a;
            letter-spacing: 0.3px;
            margin-top: 2px;
        }

        .access-key-empty {
            text-align: center;
            color: #64748b;
            font-size: 8.2px;
            padding: 8px 0;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
        }

        /* ── CARD BOXES (CLIENTE & VEHÍCULO) ── */
        .card-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            margin-bottom: 10px;
            overflow: hidden;
            background: #ffffff;
        }

        .card-header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .card-header {
            background: #f8fafc;
            color: #0f172a;
            padding: 3.5px 8px;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }

        .col-half {
            width: 50%;
        }

        .card-body {
            padding: 4px 8px;
        }

        .card-columns-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .card-col-left {
            width: 48.5%;
            vertical-align: top;
            padding-right: 6px;
        }

        .card-col-spacer {
            width: 3%;
        }

        .card-col-right {
            width: 48.5%;
            vertical-align: top;
            padding-left: 6px;
        }

        .client-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .client-table td {
            padding: 1.8px 2px;
            font-size: 8.8px;
            vertical-align: top;
            line-height: 1.25;
            text-align: left;
        }

        .c-label {
            font-weight: 700;
            color: #475569;
            width: 32%;
        }

        .c-val {
            color: #0f172a;
            width: 68%;
            word-wrap: break-word;
        }

        .c-label-veh {
            font-weight: 700;
            color: #475569;
            width: 28%;
        }

        .c-val-veh {
            color: #0f172a;
            width: 72%;
            word-wrap: break-word;
        }

        /* Versión sin vehículo */
        .client-table-novendor {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .client-table-novendor td {
            padding: 1.8px 2px;
            font-size: 8.8px;
            vertical-align: top;
            text-align: left;
        }

        .c-label-w20 {
            width: 20%;
            font-weight: 700;
            color: #475569;
        }

        .c-val-w34 {
            width: 34%;
            color: #0f172a;
            word-wrap: break-word;
        }

        .c-label-w18 {
            width: 18%;
            font-weight: 700;
            color: #475569;
        }

        .c-val-w28 {
            width: 28%;
            color: #0f172a;
            word-wrap: break-word;
        }

        .c-val-full {
            color: #0f172a;
        }

        /* ── TABLA DE PRODUCTOS / SERVICIOS ── */
        .line-items-container {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 4px 0 10px 0;
            font-size: 8.8px;
        }

        .line-items-container th {
            background: #f8fafc;
            color: #0f172a;
            border-top: 1px solid #cbd5e1;
            border-bottom: 2px solid #0f172a;
            padding: 4px 5px;
            font-size: 7.8px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.2px;
            vertical-align: middle;
        }

        .line-items-container th.th-code {
            width: 11%;
            padding: 4px 5px;
            text-align: left;
        }

        .line-items-container th.th-desc {
            font-size: 9px;
            text-align: left;
            padding: 4px 6px;
        }

        .line-items-container th.th-qty {
            width: 5%;
            padding: 4px 2px;
            font-size: 7.5px;
            text-align: center;
        }

        .line-items-container th.th-price {
            width: 9.5%;
            font-size: 7.2px;
            padding: 4px 5px;
            line-height: 1.15;
            text-align: right;
        }

        .line-items-container th.th-discount {
            width: 7%;
            font-size: 7.2px;
            padding: 4px 5px;
            line-height: 1.15;
            text-align: right;
        }

        .line-items-container th.th-tax {
            width: 7%;
            font-size: 7.2px;
            padding: 4px 5px;
            line-height: 1.15;
            text-align: right;
        }

        .line-items-container th.th-total {
            width: 10%;
            font-size: 7.2px;
            padding: 4px 5px;
            line-height: 1.15;
            text-align: right;
        }

        .th-subtext {
            font-size: 6px;
            font-weight: normal;
            color: #64748b;
            display: block;
        }

        .line-items-container td {
            padding: 3.5px 5px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 8.5px;
            color: #0f172a;
            vertical-align: middle;
            background: transparent;
        }

        .td-code {
            padding: 3.5px 5px !important;
            color: #334155;
            font-weight: 500;
            word-break: break-all;
            text-align: left;
        }

        .td-desc {
            padding: 3.5px 6px !important;
            word-wrap: break-word;
            font-size: 9.5px !important;
            font-weight: normal;
            color: #0f172a;
            line-height: 1.25;
            text-align: left;
        }

        .td-qty {
            padding: 3.5px 2px !important;
            text-align: center !important;
        }

        .td-price,
        .td-discount,
        .td-tax,
        .td-total {
            padding: 3.5px 5px !important;
            text-align: right !important;
        }

        .td-total {
            font-weight: bold;
        }

        /* ── SECCIÓN INFERIOR: INFORMACIÓN ADICIONAL Y TOTALES ── */
        .bottom-layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 6px;
        }

        .bottom-left-col {
            width: 49.25%;
            vertical-align: top;
        }

        .bottom-spacer-col {
            width: 1.5%;
        }

        .bottom-right-col {
            width: 49.25%;
            vertical-align: top;
        }

        .info-adicional-box {
            margin-bottom: 8px;
        }

        .formas-pago-box {
            margin-bottom: 0;
        }

        .info-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2.2px 3px;
            font-size: 8.5px;
        }

        .info-label {
            font-weight: 700;
            width: 34%;
            color: #475569;
        }

        .info-val {
            color: #0f172a;
        }

        /* Tabla Formas de Pago */
        .payment-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .payment-table thead tr {
            border-bottom: 1px solid #cbd5e1;
        }

        .payment-table th {
            font-size: 7.8px;
            padding-bottom: 2px;
            color: #0f172a;
            font-weight: 700;
        }

        .payment-th-desc {
            width: 48%;
            text-align: left;
        }

        .payment-th-total {
            width: 24%;
        }

        .payment-th-term {
            width: 14%;
        }

        .payment-th-time {
            width: 14%;
        }

        .payment-table td {
            font-size: 8px;
            padding: 2px 0;
        }

        .payment-td-desc {
            color: #1e293b;
            text-transform: uppercase;
        }

        .payment-td-total {
            font-size: 8.5px !important;
            color: #0f172a;
        }

        .payment-td-term,
        .payment-td-time {
            color: #475569;
        }

        /* ── TOTALES SRI ── */
        .totals-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
        }

        .totals-table td {
            padding: 3.5px 8px;
            font-size: 8.8px;
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
            font-size: 9px;
            width: 40%;
        }

        .tot-highlight {
            background: transparent !important;
            color: #0f172a !important;
            border-top: 2px solid #0f172a !important;
        }

        .tot-highlight td {
            padding: 5px 8px;
            font-size: 11px;
            font-weight: 800;
            color: #0f172a !important;
        }

        .tot-highlight .tot-label,
        .tot-highlight .tot-val {
            font-weight: 800;
            color: #0f172a !important;
        }

        /* ── PIE DE PÁGINA ── */
        .footer {
            position: fixed;
            bottom: -32px;
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

        .footer-legal {
            width: 80%;
            text-align: left;
            vertical-align: middle;
            color: #64748b;
            font-size: 6.5px;
        }

        .page-container {
            display: none;
        }
    </style>

    @if (request()->has('print'))
    <style>
        @page {
            margin: 0 !important;
        }

        .print-preview-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #1e1e2d;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .print-preview-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .preview-title {
            font-weight: 600;
            font-size: 14px;
            color: #ffffff;
            line-height: 1.2;
        }

        .preview-subtitle {
            font-size: 11px;
            color: #a1a5b7;
            margin-top: 2px;
            line-height: 1.2;
        }

        .print-preview-actions {
            display: flex;
            gap: 12px;
        }

        .print-preview-actions .btn {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-print {
            background-color: #009ef7;
            color: #ffffff;
        }

        .btn-print:hover {
            background-color: #0095e8;
        }

        .btn-close {
            background-color: #323248;
            color: #a1a5b7;
        }

        .btn-close:hover {
            background-color: #434360;
            color: #ffffff;
        }

        @media screen {
            body {
                background-color: #f5f5f9 !important;
                padding: 90px 20px 40px 20px !important;
                display: flex !important;
                justify-content: center !important;
                align-items: flex-start !important;
                min-height: 100vh !important;
            }

            .print-container {
                background: white !important;
                width: 100% !important;
                max-width: 800px !important;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
                border-radius: 8px !important;
                padding: 40px 45px !important;
                box-sizing: border-box !important;
            }
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html,
            body {
                height: auto !important;
            }

            .no-print {
                display: none !important;
            }

            body {
                display: block !important;
                min-height: auto !important;
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-container {
                padding: 0 !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
        }
    </style>
    @endif
</head>

<body>
    @php
    $sucursal = $sucursal ?? (\App\Models\Config\Sucursale::find($sale->user->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first());

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

    // Convert QR code to Base64 (si no es email)
    $qrBase64 = '';
    if (empty($isEmail)) {
    $qrPath = public_path('assets/img/brand/qr.png');
    if (file_exists($qrPath)) {
    $qrData = file_get_contents($qrPath);
    $qrBase64 = 'data:image/png;base64,' . base64_encode($qrData);
    }
    }

    // Datos SRI y numeración oficial
    $estab = str_pad($sucursal->establishment_code ?? '001', 3, '0', STR_PAD_LEFT);
    $ptoEmi = str_pad($sucursal->emission_point_code ?? '001', 3, '0', STR_PAD_LEFT);
    $cleanDocNum = preg_replace('/\D/', '', $sale->document_number);
    $secuencial = str_pad($cleanDocNum ?: $sale->id, 9, '0', STR_PAD_LEFT);
    $numeroFormateado = (strpos($sale->document_number, '-') !== false && strlen($sale->document_number) >= 15)
    ? $sale->document_number
    : "{$estab}-{$ptoEmi}-{$secuencial}";

    $fechaEmision = $sale->service_date
    ? $sale->service_date->format('d/m/Y')
    : ($sale->created_at ? $sale->created_at->format('d/m/Y') : now()->format('d/m/Y'));

    $isAutorizada = in_array(strtoupper($sale->sri_status ?? ''), ['AUTORIZADA', 'AUTORIZADO']);
    $numeroAutorizacion = $autorizacion['numeroAutorizacion']
    ?? (!empty($sale->sri_access_key) ? $sale->sri_access_key : null);
    if (empty($numeroAutorizacion)) {
    $numeroAutorizacion = ($sale->document_type === 'invoice') ? 'PENDIENTE' : 'NO APLICA';
    }

    $fechaAutorizacion = $autorizacion['fechaAutorizacion']
    ?? ($sale->sri_authorization_date ? \Carbon\Carbon::parse($sale->sri_authorization_date)->format('d/m/Y H:i:s') : null);

    $ambienteVal = (int) ($sucursal->ambiente ?? env('SRI_AMBIENTE', 1));
    $ambienteNombre = $ambienteVal === 2 ? 'PRODUCCIÓN' : 'PRUEBAS';

    $claveGenerada = !empty($sale->sri_access_key)
    ? $sale->sri_access_key
    : (($numeroAutorizacion !== 'PENDIENTE' && $numeroAutorizacion !== 'NO APLICA') ? $numeroAutorizacion : '');

    // Resolución de Ubigeo / Ciudad y Provincia del cliente
    $provinceName = $sale->client->provincia ?? '';
    $districtName = $sale->client->distrito ?? '';

    if (empty($provinceName) || is_numeric($provinceName) || empty($districtName) || is_numeric($districtName)) {
    $provId = $sale->client->ubigeo_provincia;
    $distId = $sale->client->ubigeo_distrito;

    $path = storage_path('app/ubigeo.json');
    if (file_exists($path)) {
    $json = file_get_contents($path);
    $data = json_decode($json, true) ?? [];

    foreach ($data as $region) {
    if (isset($region['provinces'])) {
    foreach ($region['provinces'] as $prov) {
    if ($prov['id'] === $provId) {
    if (empty($provinceName) || is_numeric($provinceName)) {
    $provinceName = $prov['name'];
    }
    if (isset($prov['districts'])) {
    foreach ($prov['districts'] as $dist) {
    if ($dist['id'] === $distId) {
    if (empty($districtName) || is_numeric($districtName)) {
    $districtName = $dist['name'];
    }
    break 3;
    }
    }
    }
    }
    }
    }
    }
    }
    }

    if (empty($provinceName) || is_numeric($provinceName)) {
    $provinceName = 'PICHINCHA';
    }
    if (empty($districtName) || is_numeric($districtName)) {
    $districtName = 'QUITO';
    }

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

    @if (request()->has('print'))
    <!-- Action Bar -->
    <div class="no-print print-preview-bar">
        <div class="print-preview-info">
            <span class="preview-title">Previsualización de
                {{ $sale->document_type === 'quote' ? 'Cotización' : ($sale->document_type === 'invoice' ? 'Factura' : 'Nota de Venta') }}
                #{{ $sale->document_number }}</span>
            <span class="preview-subtitle">Revisa el documento antes de imprimir</span>
        </div>
        <div class="print-preview-actions">
            <button onclick="window.print()" class="btn btn-print">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                    viewBox="0 0 16 16" style="margin-right: 6px;">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1" />
                    <path
                        d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1" />
                </svg>
                Imprimir
            </button>
            <button onclick="window.close()" class="btn btn-close">
                Cerrar
            </button>
        </div>
    </div>

    <div class="print-container">
        @endif

        <div class="web-container" style="padding-bottom: 10px;">

            <div class="page-container">
                Page <span class="page"></span> of <span class="pages"></span>
            </div>

            {{-- ═══ CABECERA SRI OFICIAL (DOS COLUMNAS PARALELAS) ═══ --}}
            <table class="sri-header-table">
                <tr>
                    {{-- COLUMNA IZQUIERDA: LOGO Y DATOS DE LA EMPRESA --}}
                    <td class="sri-header-left">
                        {{-- LOGO --}}
                        <div class="logo-box">
                            @if (!empty($logoBase64))
                            <img src="{{ $logoBase64 }}" class="logo-img" alt="Luxury Evys">
                            @endif
                        </div>

                        {{-- CUADRO INFORMACIÓN EMPRESA --}}
                        <div class="sri-box">
                            <div class="company-name">
                                {{ $sucursal->name ?? 'COMPAÑÍA DE SERVICIOS Y MANTENIMIENTO AUTOMOTRIZ LUXURY-EVYS CIA. LTDA.' }}
                            </div>

                            <table class="company-details-table">
                                <tr>
                                    <td class="comp-label">Dirección Matriz:</td>
                                    <td class="comp-val">{{ $sucursal->address ?? 'Barrio: LA ARGENTINA Calle: E1C Numero: LOTE 56 Interseccion: S49B' }}</td>
                                </tr>
                                <tr>
                                    <td class="comp-label">Dirección Sucursal:</td>
                                    <td class="comp-val">{{ $sucursal->branch_address ?? $sucursal->address ?? 'Barrio: LA ARGENTINA Calle: E1C Numero: LOTE 56 Interseccion: S49B' }}</td>
                                </tr>
                            </table>

                            <table class="company-tax-table">
                                <tr>
                                    <td class="tax-label">OBLIGADO A LLEVAR CONTABILIDAD</td>
                                    <td class="tax-val">{{ strtoupper($sucursal->obligado_contabilidad ?? 'SI') }}</td>
                                </tr>
                                @if(!empty($sucursal->contribuyente_especial))
                                <tr>
                                    <td class="tax-label">CONTRIBUYENTE ESPECIAL NRO:</td>
                                    <td class="tax-val">{{ $sucursal->contribuyente_especial }}</td>
                                </tr>
                                @endif
                                @if(!empty($sucursal->regimen_rimpe))
                                <tr>
                                    <td colspan="2" class="rimpe-label">CONTRIBUYENTE RÉGIMEN RIMPE</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </td>

                    {{-- ESPACIADOR CENTRAL --}}
                    <td class="sri-header-spacer"></td>

                    {{-- COLUMNA DERECHA: DATOS TRIBUTARIOS Y FISCALES --}}
                    <td class="sri-header-right">
                        <div class="sri-box">
                            {{-- RUC --}}
                            <div class="fiscal-ruc">
                                R.U.C.: &nbsp;&nbsp;{{ $sucursal->ruc ?? '1793192550001' }}
                            </div>

                            {{-- TIPO DE DOCUMENTO --}}
                            <div class="fiscal-doc-type">
                                {{ $sale->document_type === 'quote' ? 'COTIZACIÓN' : ($sale->document_type === 'invoice' ? 'FACTURA' : 'NOTA DE VENTA') }}
                            </div>

                            {{-- NUMERO DE DOCUMENTO --}}
                            <div class="fiscal-doc-number">
                                <span class="doc-num-label">No.</span>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                <span class="doc-num-value">{{ $numeroFormateado }}</span>
                            </div>

                            {{-- NUMERO DE AUTORIZACION --}}
                            <div class="auth-label">NÚMERO DE AUTORIZACIÓN</div>
                            <div class="auth-number">{{ $numeroAutorizacion }}</div>

                            <table class="fiscal-meta-table">
                                <tr>
                                    <td class="meta-label">FECHA Y HORA DE AUTORIZACIÓN:</td>
                                    <td class="meta-val">{{ $fechaAutorizacion ?: ($isAutorizada ? $fechaEmision : 'PENDIENTE') }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">AMBIENTE:</td>
                                    <td class="meta-val">{{ $ambienteNombre }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">EMISIÓN:</td>
                                    <td class="meta-val">NORMAL</td>
                                </tr>
                            </table>

                            {{-- CLAVE DE ACCESO Y BARCODE --}}
                            <div class="access-key-container">
                                <div class="access-key-label">CLAVE DE ACCESO</div>
                                @if(!empty($claveGenerada))
                                <div class="barcode-box">
                                    {!! \App\Helpers\PdfHelper::generateBarcodeHTML($claveGenerada, 36) !!}
                                </div>
                                <div class="access-key-digits">{{ $claveGenerada }}</div>
                                @else
                                <div class="access-key-empty">DOCUMENTO SIN CLAVE DE ACCESO</div>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- ═══ DATOS DEL RECEPTOR / CLIENTE Y VEHÍCULO ═══ --}}
            <div class="card-box">
                @if ($hasVehicle)
                <table class="card-header-table">
                    <tr>
                        <td class="card-header col-half">Información del Comprador</td>
                        <td class="card-header col-half">Información del Vehículo</td>
                    </tr>
                </table>
                <div class="card-body">
                    <table class="card-columns-table">
                        <tr>
                            {{-- COLUMNA 1: INFORMACIÓN DEL CLIENTE --}}
                            <td class="card-col-left">
                                <table class="client-table">
                                    <tr>
                                        <td class="c-label">Razón Social:</td>
                                        <td class="c-val bold">{{ $sale->client->full_name ?? $sale->client->name ?? 'CONSUMIDOR FINAL' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="c-label">
                                            @if (($sale->client->type_document ?? '') == 1)
                                            Cédula / CI:
                                            @elseif(($sale->client->type_document ?? '') == 2)
                                            R.U.C.:
                                            @else
                                            Identificación:
                                            @endif
                                        </td>
                                        <td class="c-val bold">{{ $sale->client->n_document ?? '9999999999999' }}</td>
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

                            {{-- ESPACIADOR CENTRAL --}}
                            <td class="card-col-spacer"></td>

                            {{-- COLUMNA 2: INFORMACIÓN DEL VEHÍCULO --}}
                            <td class="card-col-right">
                                <table class="client-table">
                                    <tr>
                                        <td class="c-label-veh">Placa:</td>
                                        <td class="c-val-veh bold">{{ $vehicle->license_plate ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="c-label-veh">Vehículo:</td>
                                        <td class="c-val-veh uppercase">{{ $vehicleFull ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="c-label-veh">Año / Tipo:</td>
                                        <td class="c-val-veh">{{ ($vehicleYear ?: '-') . ' / ' . ($vehicleType ?: '-') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="c-label-veh">Kilometraje:</td>
                                        <td class="c-val-veh">{{ !empty($mileageVal) ? number_format($mileageVal) . ' km' : 'N/A' }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
                @else
                <div class="card-header">Información del Comprador</div>
                <div class="card-body">
                    <table class="client-table-novendor">
                        <tr>
                            <td class="c-label-w20">Razón Social:</td>
                            <td class="c-val-w34 bold">{{ $sale->client->full_name ?? $sale->client->name ?? 'CONSUMIDOR FINAL' }}</td>
                            <td class="c-label-w18">Identificación:</td>
                            <td class="c-val-w28 bold">{{ $sale->client->n_document ?? '9999999999999' }}</td>
                        </tr>
                        <tr>
                            <td class="c-label-w20">Fecha Emisión:</td>
                            <td class="c-val-w34">{{ $fechaEmision }}</td>
                            <td class="c-label-w18">Teléfono:</td>
                            <td class="c-val-w28">{{ $sale->client->phone ?? $sale->client->cellphone ?? 'Sin información' }}</td>
                        </tr>
                        <tr>
                            <td class="c-label-w20">Dirección:</td>
                            <td class="c-val-full" colspan="3">{{ $sale->client->address ?? 'SUR DE QUITO' }}</td>
                        </tr>
                    </table>
                </div>
                @endif
            </div>

            @php
            $hasAnyDiscount = $sale->details->some(fn($d) => ($d->discount ?? 0) > 0);
            $descWidth = $hasAnyDiscount ? '41%' : '48%';
            @endphp
            <table class="line-items-container">
                <thead>
                    <tr>
                        <th class="th-code">Código</th>
                        <th class="th-desc" style="width: {{ $descWidth }};">Descripción</th>
                        <th class="th-qty center">Cant.</th>
                        <th class="th-price right">
                            P. Unit.<br><span class="th-subtext">(Sin IVA)</span>
                        </th>
                        <th class="th-price right">
                            Subtotal<br><span class="th-subtext">(Sin IVA)</span>
                        </th>
                        @if($hasAnyDiscount)
                        <th class="th-discount right">Desc.</th>
                        @endif
                        <th class="th-tax right">
                            IVA<br><span class="th-subtext">(15%)</span>
                        </th>
                        <th class="th-total right">
                            Total<br><span class="th-subtext">(Con IVA)</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->details as $detail)
                    @php
                    $unitPriceSinIva = $detail->price / 1.15;
                    $subtotalItemSinIva = $unitPriceSinIva * $detail->quantity;
                    $discountSinIva = ($detail->discount ?? 0) / 1.15;
                    $subtotalNetoSinIva = $subtotalItemSinIva - $discountSinIva;
                    $ivaItem = $subtotalNetoSinIva * 0.15;
                    $totalItemConIva = ($detail->price * $detail->quantity) - ($detail->discount ?? 0);
                    $productCode = $detail->product?->sku ?: ($detail->product_id ? str_pad($detail->product_id, 4, '0', STR_PAD_LEFT) : '-');
                    @endphp
                    <tr>
                        <td class="td-code">{{ $productCode }}</td>
                        <td class="td-desc">{{ $detail->description }}</td>
                        <td class="td-qty center">{{ $detail->quantity }}</td>
                        <td class="td-price right">${{ number_format($unitPriceSinIva, 2) }}</td>
                        <td class="td-price right">${{ number_format($subtotalItemSinIva, 2) }}</td>
                        @if($hasAnyDiscount)
                        <td class="td-discount right">${{ number_format($discountSinIva, 2) }}</td>
                        @endif
                        <td class="td-tax right">${{ number_format($ivaItem, 2) }}</td>
                        <td class="td-total right bold">${{ number_format($totalItemConIva, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ═══ SECCIÓN INFERIOR: INFORMACIÓN ADICIONAL Y TOTALES (2 COLUMNAS OFICIALES SRI) ═══ --}}
            <table class="layout-table bottom-layout-table">
                <tr>
                    {{-- INFORMACIÓN ADICIONAL & FORMAS DE PAGO --}}
                    <td class="bottom-left-col">
                        <div class="card-box info-adicional-box">
                            <div class="card-header">Información Adicional</div>
                            <div class="card-body">
                                <table class="info-table">
                                    @php
                                    $otNumber = $sale->work_order_number ?: ($sale->workOrder->number ?? null);
                                    $otDate = $sale->workOrder ? $sale->workOrder->date : null;
                                    $otDateFormatted = $otDate ? \Carbon\Carbon::parse($otDate)->format('d/m/Y') : null;
                                    @endphp
                                    @if (!empty($otNumber))
                                    <tr>
                                        <td class="info-label">Orden de Trabajo:</td>
                                        <td class="info-val bold">#{{ $otNumber }}</td>
                                    </tr>
                                    @if (!empty($otDateFormatted) && $otDateFormatted !== $fechaEmision)
                                    <tr>
                                        <td class="info-label">Fecha O/T:</td>
                                        <td class="info-val semibold">{{ $otDateFormatted }}</td>
                                    </tr>
                                    @endif
                                    @endif


                                    <tr>
                                        <td class="info-label">Teléfono:</td>
                                        <td class="info-val">{{ $sale->client->phone ?? $sale->client->cellphone ?? 'Sin información' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Email:</td>
                                        <td class="info-val">{{ $sale->client->email ?? 'Sin información' }}</td>
                                    </tr>
                                    @if (!empty($sale->observations))
                                    <tr>
                                        <td class="info-label valign-top">Observaciones:</td>
                                        <td class="info-val">{{ $sale->observations }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="card-box formas-pago-box">
                            <div class="card-header">Formas de Pago</div>
                            <div class="card-body">
                                <table class="payment-table">
                                    <thead>
                                        <tr>
                                            <th class="payment-th-desc">Forma de Pago</th>
                                            <th class="payment-th-total right">Total</th>
                                            <th class="payment-th-term center">Plazo</th>
                                            <th class="payment-th-time center">Tiempo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                        $mapSriFormaPago = function($method, $account = null) {
                                        $m = strtolower(trim((string)$method));
                                        $accType = strtolower(trim((string)($account?->type ?? '')));
                                        $accName = strtolower(trim((string)($account?->name ?? '')));

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
                                        } elseif (isset($sale->payments) && $sale->payments->count() > 0) {
                                        foreach ($sale->payments as $p) {
                                        $formasPago[] = [
                                        'descripcion' => $mapSriFormaPago($p->method_payment ?? ($p->payment_method ?? 'cash')),
                                        'total' => (float)$p->amount,
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
                                            <td class="payment-td-desc">{{ $fp['descripcion'] }}</td>
                                            <td class="payment-td-total right">${{ number_format($fp['total'], 2) }}</td>
                                            <td class="payment-td-term center">{{ $fp['plazo'] }}</td>
                                            <td class="payment-td-time center">{{ $fp['tiempo'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>

                    {{-- ESPACIADOR CENTRAL --}}
                    <td class="bottom-spacer-col"></td>

                    {{-- TOTALES OFICIALES SRI --}}
                    <td class="bottom-right-col">
                        @php
                        $calculatedGrossSubtotal = $sale->details->sum(function ($item) {
                        return $item->quantity * ($item->price / 1.15);
                        });
                        $calculatedTotalDiscount = $sale->details->sum(function ($item) {
                        return ($item->discount ?? 0) / 1.15;
                        });
                        $calculatedNetSubtotal = $calculatedGrossSubtotal - $calculatedTotalDiscount;
                        $calculatedTotal = $sale->details->sum(function ($item) {
                        return ($item->price * $item->quantity) - ($item->discount ?? 0);
                        });
                        $calculatedIva = $calculatedTotal - $calculatedNetSubtotal;
                        @endphp
                        <table class="totals-table">
                            <tr>
                                <td class="tot-label">Subtotal 15%</td>
                                <td class="tot-val">${{ number_format($calculatedNetSubtotal, 2) }}</td>
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
                                <td class="tot-val">${{ number_format($calculatedNetSubtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="tot-label">Total Descuento</td>
                                <td class="tot-val">${{ number_format($calculatedTotalDiscount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="tot-label">IVA 15%</td>
                                <td class="tot-val">${{ number_format($calculatedIva, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="tot-label">Propina</td>
                                <td class="tot-val">$0.00</td>
                            </tr>
                            <tr class="tot-highlight">
                                <td class="tot-label">VALOR TOTAL</td>
                                <td class="tot-val">${{ number_format($calculatedTotal, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ═══ PIE DE PÁGINA FIJO ═══ --}}
            <div class="footer">
                <table class="footer-table">
                    <tr>
                        <td class="footer-legal">
                            Este documento es una representación impresa de un Comprobante Electrónico (RIDE) de acuerdo al Art. 21 del Reglamento de Comprobantes de Venta y Retención.<br>
                            Para consultar la validez del comprobante, ingrese a <strong>https://sri.gob.ec</strong>.
                        </td>
                        <td style="width: 20%; text-align: right; vertical-align: middle;">
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

        @if (request()->has('print'))
    </div>

    <script>
        function triggerPrint() {
            setTimeout(() => {
                window.print();
            }, 600);
        }
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            triggerPrint();
        } else {
            window.addEventListener('DOMContentLoaded', triggerPrint);
        }
    </script>
    @endif
</body>

</html>
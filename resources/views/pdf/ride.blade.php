<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>RIDE - Factura {{ $sale->document_number }}</title>
    <style>
        @page {
            /* margin: 12px 14px 44px 14px; */
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5px;
            color: #334155;
            background: #ffffff;
            line-height: 1.25;
            padding: 0;
            margin: 0;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── HEADER TWO-COLUMN BOXES ───────────────────────────── */
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

        .logo-box {
            text-align: left;
            margin-bottom: 8px;
            height: 82px;
        }

        .logo-img {
            max-height: 80px;
            max-width: 230px;
            object-fit: contain;
            display: block;
            margin: 0 0 2px 0;
        }

        .company-title {
            font-size: 9.5px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .company-subtitle {
            font-size: 7px;
            color: #64748b;
            margin-bottom: 3px;
            line-height: 1.2;
        }

        .company-detail {
            font-size: 7.5px;
            color: #475569;
            margin-bottom: 1.5px;
        }

        .company-detail strong {
            color: #1e293b;
        }

        /* Right column headers */
        .doc-ruc {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .doc-type-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            letter-spacing: 1.2px;
            margin-bottom: 2px;
        }

        .doc-number {
            font-size: 9px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }

        .auth-label {
            font-size: 6.8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .auth-value {
            font-size: 8px;
            color: #5c77a3ff;
            word-break: break-all;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .meta-grid td {
            font-size: 7px;
            padding: 1px 0;
            color: #334155;
        }

        .meta-grid strong {
            color: #475569;
        }

        .barcode-container {
            text-align: center;
            margin-top: 3px;
            padding: 3px 4px 2px 4px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .access-key-text {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 6.5px;
            letter-spacing: 0.3px;
            color: #334155;
            margin-top: 2px;
            text-align: center;
            font-weight: 600;
        }

        /* ── CARD BOXES (CLIENT & DETAILS) ─────────────────────── */
        .card-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            margin-bottom: 8px;
            overflow: hidden;
            background: #ffffff;
        }

        .card-header {
            background: #475569;
            color: #ffffff;
            padding: 3.5px 10px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .card-body {
            padding: 6px 10px;
        }

        .client-table {
            width: 100%;
            border-collapse: collapse;
        }

        .client-table td {
            padding: 2px 3px;
            font-size: 7.5px;
            vertical-align: middle;
        }

        .c-label {
            font-weight: 700;
            color: #64748b;
            width: 18%;
        }

        .c-val {
            color: #1e293b;
            width: 32%;
        }

        /* ── DETAILS TABLE ─────────────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
        }

        .items-table thead th {
            background: #475569;
            color: #ffffff;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 6px;
            text-align: center;
            border-right: 1px solid #64748b;
            letter-spacing: 0.3px;
        }

        .items-table thead th:last-child {
            border-right: none;
        }

        .items-table tbody td {
            padding: 3.5px 6px;
            font-size: 7.5px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .items-table tbody td:last-child {
            border-right: none;
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
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2px 3px;
            font-size: 7.5px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
        }

        .totals-table td {
            padding: 2.5px 8px;
            font-size: 7.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .tot-label {
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .tot-val {
            text-align: right;
            font-weight: 700;
            color: #1e293b;
        }

        .tot-highlight {
            background: #475569 !important;
            color: #ffffff !important;
        }

        .tot-highlight td {
            padding: 4px 8px;
            font-size: 8.5px;
            font-weight: 700;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
            color: #ffffff !important;
        }

        .tot-highlight .tot-label {
            font-weight: 700;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
            color: #ffffff !important;
        }

        .tot-highlight .tot-val {
            font-weight: 700;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
            color: #ffffff !important;
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
            font-size: 6.5px;
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
    @endphp

    <div class="container">

        {{-- ═══ CABECERA SRI (2 COLUMNAS OFICIALES) ════════════════════════ --}}
        <table class="layout-table">
            <tr>
                {{-- COLUMNA IZQUIERDA: DATOS DE LA EMPRESA --}}
                <td style="width: 48%; vertical-align: top;">
                    @if (!empty($logoBase64))
                    <div style="text-align: left; margin-bottom: 6px;">
                        <img src="{{ $logoBase64 }}" style="height: 86px; width: auto; max-width: 235px; object-fit: contain; display: block; margin: 0 0 2px 0;" alt="Logo">
                    </div>
                    @endif
                    <div class="card-panel">
                        <div class="company-title">{{ $sucursal->trade_name ?? $sucursal->name ?? 'LUXURY EVYS' }}</div>
                        @if (!empty($sucursal->trade_name) && $sucursal->trade_name !== $sucursal->name)
                        <div class="company-subtitle">{{ $sucursal->name }}</div>
                        @endif

                        <div class="company-detail" style="margin-top: 4px;">
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
                        <div class="company-detail" style="margin-top: 2px;">
                            <strong>Obligado a Llevar Contabilidad:</strong>
                            <span style="font-weight: 700; color: #475569;">{{ strtoupper($sucursal->obligado_contabilidad ?? 'SI') }}</span>
                        </div>
                        @if (!empty($sucursal->contribuyente_especial))
                        <div class="company-detail">
                            <strong>Contribuyente Especial Nro:</strong> {{ $sucursal->contribuyente_especial }}
                        </div>
                        @endif
                        @if (!empty($sucursal->regimen_rimpe))
                        <div class="company-detail" style="color: #475569; font-weight: 700;">
                            CONTRIBUYENTE RÉGIMEN RIMPE
                        </div>
                        @endif
                    </div>
                </td>

                {{-- ESPACIADOR CENTRAL --}}
                <td style="width: 4%;"></td>

                {{-- COLUMNA DERECHA: DATOS TRIBUTARIOS Y FISCALES --}}
                <td style="width: 48%; vertical-align: top;">
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
                                <td style="width: 50%;">
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
                                <td>NORMAL</td>
                            </tr>
                        </table>

                        <div class="barcode-container">
                            <div class="auth-label" style="margin-top: 0; margin-bottom: 1px;">CLAVE DE ACCESO</div>
                            @if (!empty($sale->sri_access_key))
                            <div style="padding: 1px 0;">
                                {!! \App\Helpers\PdfHelper::generateBarcodeHTML($sale->sri_access_key, 22) !!}
                            </div>
                            <div class="access-key-text">{{ $sale->sri_access_key }}</div>
                            @else
                            <div style="color: #94a3b8; font-size: 6.5px;">SIN CLAVE DE ACCESO</div>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- ═══ DATOS DEL RECEPTOR / CLIENTE ════════════════════════════════ --}}
        <div class="card-box">
            <div class="card-header">Información del Comprador</div>
            <div class="card-body">
                <table class="client-table">
                    <tr>
                        <td class="c-label">Razón Social / Nombres:</td>
                        <td class="c-val" style="font-weight: 700;">{{ $sale->client->full_name ?? $sale->client->name ?? 'CONSUMIDOR FINAL' }}</td>
                        <td class="c-label">Identificación:</td>
                        <td class="c-val" style="font-weight: 700;">{{ $sale->client->n_document ?? '9999999999999' }}</td>
                    </tr>
                    <tr>
                        <td class="c-label">Fecha Emisión:</td>
                        <td class="c-val">{{ $fechaEmision }}</td>
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
                        <td class="c-label">Dirección:</td>
                        <td class="c-val" colspan="3">{{ $sale->client->address ?? 'SUR DE QUITO' }}</td>
                    </tr>
                    @if ($vehicle)
                    <tr>
                        <td class="c-label">Vehículo / Placa:</td>
                        <td class="c-val" style="text-transform: uppercase;">
                            {{ $vehicleFull ?: 'Vehículo' }}
                            @if(!empty($vehicle->license_plate))
                            / <strong> {{ $vehicle->license_plate }}</strong>
                            @endif
                        </td>
                        <td class="c-label">Kilometraje:</td>
                        <td class="c-val">{{ $mileageVal ? number_format($mileageVal) . ' km' : 'N/A' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- ═══ TABLA DE DETALLES (PRODUCTOS / SERVICIOS) ════════════════════ --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Cod. Principal</th>
                    <th style="width: 8%;">Cant.</th>
                    <th style="width: 44%; text-align: left;">Descripción</th>
                    <th style="width: 12%;">Precio Unit.</th>
                    <th style="width: 10%;">Descuento</th>
                    <th style="width: 14%;">Precio Total</th>
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
                    <td class="text-center">{{ $codPrincipal }}</td>
                    <td class="text-center">{{ number_format($qty, 2) }}</td>
                    <td class="text-left" style="font-family: 'sans-serif'; font-size: 8px;">{{ $detalle->description }}</td>
                    <td class="text-right">${{ number_format($unitSinImpuesto, 4) }}</td>
                    <td class="text-right">${{ number_format($discount, 2) }}</td>
                    <td class="text-right" style="font-weight: 700;">${{ number_format($subtotalItem, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="border-top: 1px solid #cbd5e1; margin: 8px 0 10px 0;"></div>
        {{-- ═══ SECCIÓN INFERIOR: INFORMACIÓN ADICIONAL Y TOTALES ═══════════ --}}
        <table class="layout-table" style="margin-bottom: 4px;">
            <tr>
                {{-- INFORMACIÓN ADICIONAL & FORMAS DE PAGO --}}
                <td style="width: 52%; vertical-align: top;">
                    <div class="card-box" style="margin-bottom: 6px;">
                        <div class="card-header">Información Adicional</div>
                        <div class="card-body">
                            <table class="info-table">
                                @php
                                $otNumber = $sale->work_order_number ?: ($sale->workOrder->number ?? null);
                                @endphp
                                @if (!empty($otNumber))
                                <tr>
                                    <td style="font-weight: 700; width: 28%; color: #64748b;">Orden de Trabajo:</td>
                                    <td style="color: #1e293b; font-weight: 700;">{{ $otNumber }}</td>
                                </tr>
                                @endif
                                @if ($vehicle)
                                @if (!empty($vehicle->license_plate))
                                <tr>
                                    <td style="font-weight: 700; width: 28%; color: #64748b;">Placa:</td>
                                    <td style="color: #1e293b; font-weight: 700;">{{ $vehicle->license_plate }}</td>
                                </tr>
                                @endif
                                @if (!empty($vehicleFull))
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Vehículo:</td>
                                    <td style="color: #1e293b;">{{ $vehicleFull }}</td>
                                </tr>
                                @endif
                                @if (!empty($vehicleYear))
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Año del Vehículo:</td>
                                    <td style="color: #1e293b;">{{ $vehicleYear }}</td>
                                </tr>
                                @endif
                                @if (!empty($vehicleType))
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Tipo:</td>
                                    <td style="color: #1e293b;">{{ $vehicleType }}</td>
                                </tr>
                                @endif
                                @endif
                                @if (!empty($mileageVal))
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Kilometraje:</td>
                                    <td style="color: #1e293b;">{{ number_format($mileageVal) }} km</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="font-weight: 700; width: 28%; color: #64748b;">Email:</td>
                                    <td style="color: #1e293b;">{{ $sale->client->email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Teléfono:</td>
                                    <td style="color: #1e293b;">{{ $sale->client->phone ?? $sale->client->cellphone ?? 'N/A' }}</td>
                                </tr>
                                @if (!empty($sale->observations))
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">Observaciones:</td>
                                    <td style="color: #1e293b;">{{ $sale->observations }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <div class="card-box" style="margin-bottom: 0;">
                        <div class="card-header">Formas de Pago</div>
                        <div class="card-body" style="padding: 4px 10px;">
                            <table class="info-table">
                                <thead>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <th style="text-align: left; font-size: 7px; padding-bottom: 2px; color: #64748b; font-weight: 700;">Forma de Pago</th>
                                        <th style="text-align: right; font-size: 7px; padding-bottom: 2px; color: #64748b; font-weight: 700;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="font-size: 7.5px; font-weight: 600; color: #334155;">{{ $sale->payment_method ?? 'Sin utilización del sistema financiero' }}</td>
                                        <td style="text-align: right; font-weight: 700; font-size: 8px; color: #1e293b;">${{ number_format((float)$sale->total, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>

                {{-- ESPACIADOR CENTRAL --}}
                <td style="width: 4%;"></td>

                {{-- TOTALES OFICIALES SRI --}}
                <td style="width: 44%; vertical-align: top;">
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
                            <td class="tot-val">${{ number_format($descuentoTotal, 2) }}</td>
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
            $font = $fontMetrics->getFont("Helvetica, Arial, sans-serif", "bold");
            $size = 6.8;
            $color = [0.28, 0.33, 0.41]; // #475569
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $pdf->page_text(510, 818, $text, $font, $size, $color);
        }
    </script>
</body>

</html>
<!doctype html>
<html class="no-js" lang="es">

<head>
    <meta charset="utf-8">
    <title>Cotización #{{ $quote->document_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        :root {
            -moz-tab-size: 4;
            tab-size: 4;
        }

        html {
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            font-family:
                system-ui,
                -apple-system,
                'Segoe UI',
                Roboto,
                Helvetica,
                Arial,
                sans-serif,
                'Apple Color Emoji',
                'Segoe UI Emoji';
        }

        hr {
            height: 0;
            color: inherit;
        }

        b,
        strong {
            font-weight: bolder;
        }

        code,
        kbd,
        samp,
        pre {
            font-family:
                ui-monospace,
                SFMono-Regular,
                Consolas,
                'Liberation Mono',
                Menlo,
                monospace;
            font-size: 1em;
        }

        small {
            font-size: 80%;
        }

        sub,
        sup {
            font-size: 75%;
            line-height: 0;
            position: relative;
            vertical-align: baseline;
        }

        sub {
            bottom: -0.25em;
        }

        sup {
            top: -0.5em;
        }

        table {
            text-indent: 0;
            border-color: inherit;
        }

        button,
        input,
        optgroup,
        select,
        textarea {
            font-family: inherit;
            font-size: 100%;
            line-height: 1.15;
            margin: 0;
        }

        button,
        select {
            text-transform: none;
        }

        button,
        [type='button'],
        [type='reset'],
        [type='submit'] {
            -webkit-appearance: button;
        }

        ::-moz-focus-inner {
            border-style: none;
            padding: 0;
        }

        :-moz-focusring {
            outline: 1px dotted ButtonText;
        }

        :-moz-ui-invalid {
            box-shadow: none;
        }

        legend {
            padding: 0;
        }

        progress {
            vertical-align: baseline;
        }

        ::-webkit-inner-spin-button,
        ::-webkit-outer-spin-button {
            height: auto;
        }

        [type='search'] {
            -webkit-appearance: textfield;
            outline-offset: -2px;
        }

        ::-webkit-search-decoration {
            -webkit-appearance: none;
        }

        ::-webkit-file-upload-button {
            -webkit-appearance: button;
            font: inherit;
        }

        summary {
            display: list-item;
        }
    </style>
    <style>
        @page {
            margin: 8mm 15mm 18mm 15mm;
        }

        body {
            font-size: 10px;
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

        table tr td:last-child {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .large {
            font-size: 1.2em;
        }

        .total {
            font-weight: bold;
            color: #fb7578;
        }

        .total_cancelar {
            font-size: 1.45em;
            font-weight: bold;
        }

        .total_cancelar_value {
            color: #d32f2f;
        }

        .logo-container {
            margin: 0px 0 10px 0;
        }

        .invoice-info-container {
            font-size: 8px;
        }

        .invoice-info-container td {
            padding: 4px 0;
        }

        .client-name {
            font-size: 1.5em;
            vertical-align: top;
        }

        .line-items-container {
            margin: 15px 0;
            font-size: 8px;
        }

        .line-items-container th {
            text-align: left;
            color: #999;
            border-bottom: 2px solid #ddd;
            padding: 10px 0 15px 0;
            font-size: 0.75em;
            text-transform: uppercase;
        }

        .line-items-container th:last-child {
            text-align: right;
        }

        .line-items-container td {
            padding: 5px 0;
        }

        .line-items-container tbody tr:first-child td {
            padding-top: 10px;
        }

        .line-items-container.has-bottom-border tbody tr:last-child td {
            padding-bottom: 25px;
            border-bottom: 2px solid #ddd;
        }

        .line-items-container.has-bottom-border {
            margin-bottom: 0;
        }

        .line-items-container th.heading-quantity {
            width: 70px;
            text-align: center;
        }

        .line-items-container th.heading-item {
            width: 30px;
            text-align: center;
        }

        .line-items-container th.heading-price {
            text-align: right;
            width: 80px;
        }

        .line-items-container th.heading-subtotal {
            text-align: right;
            width: 100px;
        }

        .payment-info {
            width: 38%;
            font-size: 0.75em;
            line-height: 1.5;
        }

        .footer {
            margin-top: 30px;
        }

        .footer-info {
            float: right;
            margin-top: 5px;
            font-size: 0.75em;
            color: #ccc;
        }

        .footer-info span {
            padding: 0 5px;
            color: black;
        }

        .footer-info span:last-child {
            padding-right: 0;
        }

        .page-container {
            display: none;
        }

        .page-break {
            page-break-after: always;
        }

        .number-clausulas p {
            margin: 0;
            text-align: justify;
            font-size: 0.76rem;
        }

        .number-clausulas strong {
            float: left;
            font-size: 0.76rem;
        }

        .number-clausulas ul li {
            font-size: 0.76rem;
        }

        .place-date {
            text-align: right;
        }

        .place-date p {
            font-size: 0.6rem;
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
                padding: 10mm 20mm 20mm 20mm !important;
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
    $sucursal = \App\Models\Config\Sucursale::find($quote->user->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();

    // Convert branch/sucursal logo to Base64 to avoid exposing absolute local path inside the PDF
    $logoBase64 = '';
    $logoPath = null;
    if ($sucursal && $sucursal->logo) {
        $tempPath = public_path($sucursal->logo);
        if (file_exists($tempPath)) {
            $logoPath = $tempPath;
        } else {
            $cleanLogo = str_replace('storage/', '', $sucursal->logo);
            $tempPath = storage_path('app/public/' . $cleanLogo);
            if (file_exists($tempPath)) {
                $logoPath = $tempPath;
            }
        }
    }

    if (!$logoPath || !file_exists($logoPath)) {
        $logoPath = public_path('assets/img/brand/logo.jpeg');
    }

    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoMime = 'image/jpeg';
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $logoMime = 'image/png';
        } elseif ($ext === 'gif') {
            $logoMime = 'image/gif';
        } elseif ($ext === 'svg') {
            $logoMime = 'image/svg+xml';
        }
        $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
    }

    // Convert QR code to Base64
    $qrBase64 = '';
    if (empty($isEmail)) {
        $qrPath = public_path('assets/img/brand/qr.png');
        if (file_exists($qrPath)) {
            $qrData = file_get_contents($qrPath);
            $qrBase64 = 'data:image/png;base64,' . base64_encode($qrData);
        }
    }
    @endphp
    @if (request()->has('print'))
    <!-- Action Bar -->
    <div class="no-print print-preview-bar">
        <div class="print-preview-info">
            <span class="preview-title">Previsualización de Cotización #{{ $quote->document_number }}</span>
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

        <div class="web-container" style="padding-bottom: 50px;">

            <div class="page-container">
                Page <span class="page"></span> of <span class="pages"></span>
            </div>

            <div class="logo-container">
                <table style="width: 100%; border-collapse: collapse; border: none !important;">
                    <tbody>
                        <tr style="border: none !important;">
                            <td style="padding: 0 !important; border: none !important;">
                                <img style="height: 80px; border: none !important; outline: none !important;"
                                    src="{{ $logoBase64 }}">
                            </td>

                            <td style="padding: 0 !important; border: none !important;">
                                <strong>{{ $quote->document_number }}</strong>
                                <br>
                                @if ($qrBase64)
                                <img style="width:60px; border: none !important; outline: none !important;"
                                    src="{{ $qrBase64 }}">
                                @endif
                                <br>
                                <small>RUC: {{ $sucursal->ruc ?? '1793192550001' }}</small>
                                <br>
                                <small>https://www.luxuryevys.com</small>
                                <br>
                                <small>{{ $sucursal->email ?? 'comp.luxuryevys@gmail.com' }}</small>
                                <br>
                                <small>Telf: {{ $sucursal->phone ?? '0999179988 / 0963089601' }}</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="clear:both;"></div>
            
            <table class="invoice-info-container"
                style="width: 100%; margin-bottom: 10px; border-collapse: collapse; text-transform: uppercase;">
                <tr style="border-bottom: 0.8px solid #949494;">
                    <td style="width: 50%; padding-bottom: 8px; font-size: 12px; text-align: left; border: none;">
                        COTIZACIÓN# {{ $quote->id }}-{{ $quote->document_number }}
                        @if($quote->work_order_id)
                        <br><span style="font-size: 8px; color: #555; font-weight: normal;">ORDEN TRABAJO: #{{ $quote->workOrder->number ?? $quote->work_order_id }}</span>
                        @endif
                    </td>
                    <td style="width: 50%; padding-bottom: 8px; text-align: right; font-size: 12px; border: none; vertical-align: top;">
                        FECHA: {{ \Carbon\Carbon::parse($quote->service_date)->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td style="width: 48%; vertical-align: top; padding-right: 15px; padding-top: 15px; border: none;">
                        <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">
                            DATOS DEL CLIENTE</div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">NOMBRE:</span> {{ $quote->client->full_name ?? $quote->client->name }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">
                                @if (isset($quote->client->type_document) && $quote->client->type_document == 1)
                                CI #:
                                @else
                                RUC #:
                                @endif
                            </span> {{ $quote->client->n_document ?? '-' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">EMAIL:</span>
                            <span style="text-transform: lowercase;">{{ $quote->client->email ?? 'Sin información' }}</span>
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">TELÉFONO:</span>
                            {{ $quote->client->phone ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">DIRECCIÓN:</span>
                            {{ $quote->client->address ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">CIUDAD/PROVINCIA:</span>
                            @php
                                $provinceName = $quote->client->provincia ?? '';
                                $districtName = $quote->client->distrito ?? '';

                                if (empty($provinceName) || is_numeric($provinceName) || empty($districtName) || is_numeric($districtName)) {
                                    $provId = $quote->client->ubigeo_provincia ?? null;
                                    $distId = $quote->client->ubigeo_distrito ?? null;
                                    
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
                            @endphp
                            {{ strtoupper($provinceName) }}/{{ strtoupper($districtName) }}
                        </div>
                    </td>
                    <td style="width: 48%; vertical-align: top; padding-left: 15px; padding-top: 15px; border: none; text-align: left;">
                        @if ($quote->vehicle)
                        <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">
                            DATOS DEL VEHÍCULO</div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">PLACA:</span>
                            {{ $quote->vehicle->license_plate ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">MARCA:</span>
                            @php
                            $brandName = '';
                            if ($quote->vehicle->brand) {
                                $brandId = is_object($quote->vehicle->brand) ? $quote->vehicle->brand->id : $quote->vehicle->brand;
                                $vehicleBrands = config('vehicle_brands', []);
                                $brandName = $vehicleBrands[$brandId] ?? (is_object($quote->vehicle->brand) ? $quote->vehicle->brand->name : $quote->vehicle->brand);
                            }
                            @endphp
                            {{ $brandName ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">MODELO:</span>
                            {{ $quote->vehicle->model ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">AÑO:</span>
                            {{ $quote->vehicle->year ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">TIPO:</span>
                            {{ $quote->vehicle->vehicle_type ? mb_strtoupper(config('vehicle_types')[(int)$quote->vehicle->vehicle_type] ?? $quote->vehicle->vehicle_type, 'UTF-8') : 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">KILOMETRAJE:</span>
                            {{ $quote->mileage ? $quote->mileage . ' km' : 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">COLOR:</span>
                            {{ $quote->vehicle->color ?? 'Sin información' }}
                        </div>
                        @else
                        <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">
                            INFORMACIÓN ADICIONAL</div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">SUCURSAL:</span>
                            {{ $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.') }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">DIRECCIÓN:</span>
                            {{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}
                        </div>
                        @endif
                    </td>
                </tr>
                <tr style="border-top: 0.8px solid #949494ff;">
                    <td style="padding-top: 8px; border: none; font-size: 9.5px; text-align: left;">
                        CREADO POR: <strong>{{ $quote->user->name ?? 'Vendedor' }}</strong>
                    </td>
                    <td style="padding-top: 8px; text-align: right; border: none; font-size: 9.5px;">
                        TELÉFONO: {{ $quote->user->phone ?? '022698134' }}
                    </td>
                </tr>
                @if ($quote->technicians && $quote->technicians->count() > 0)
                <tr>
                    <td colspan="2" style="padding-top: 4px; border: none; font-size: 9.5px; text-align: left;">
                        TÉCNICO(S):
                        @foreach ($quote->technicians as $technician)
                        <b>{{ $technician->first_name }} {{ $technician->last_name }}</b>@if (!$loop->last), @endif
                        @endforeach
                    </td>
                </tr>
                @endif
                @if ($quote->vehicle)
                <tr>
                    <td colspan="2" style="padding-top: 6px; font-size: 8px; color: #555; border: none; text-align: left;">
                        SUCURSAL DE ATENCION: <strong>{{ $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.') }}</strong><br>
                        DIRECCIÓN: <strong>{{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}</strong>
                    </td>
                </tr>
                @endif
            </table>

            <table class="line-items-container">
                <thead>
                    <tr>
                        <th class="heading-item center">#</th>
                        <th class="heading-description">Descripción</th>
                        <th class="heading-quantity center">Cantidad</th>
                        <th class="heading-price right">PVP (Sin IVA)</th>
                        <th class="heading-price right">Descuento</th>
                        <th class="heading-price right">IVA (15%)</th>
                        <th class="heading-subtotal right">Total (Con IVA)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $cont = 0;
                    @endphp
                    @foreach ($quote->details as $detail)
                    @php
                    // Los precios en BD ya incluyen IVA, mostramos base sin IVA
                    $displayPrice = $detail->price / 1.15;
                    $displayDiscount = ($detail->discount ?? 0) / 1.15;
                    $displaySubtotalNeto = ($displayPrice * $detail->quantity) - $displayDiscount;
                    $displayIva = $displaySubtotalNeto * 0.15;
                    $displayTotal = $displaySubtotalNeto + $displayIva;
                    @endphp
                    <tr>
                        <td class="center">{{ $cont = $cont + 1 }}</td>
                        <td>
                            {{ $detail->description }}
                            @if ($detail->product?->sku)
                            <br>
                            <small style="color: #666;">Cod.: {{ $detail->product->sku }}</small>
                            @endif
                        </td>
                        <td class="center">{{ $detail->quantity }}</td>
                        <td class="right">${{ number_format($displayPrice, 2) }}</td>
                        <td class="right">${{ number_format($displayDiscount, 2) }}</td>
                        <td class="right">${{ number_format($displayIva, 2) }}</td>
                        <td class="right bold">${{ number_format($displayTotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <hr>
            
            @php
            $calculatedGrossSubtotal = $quote->details->sum(function ($item) {
                return $item->quantity * ($item->price / 1.15);
            });
            $calculatedTotalDiscount = $quote->details->sum(function ($item) {
                return ($item->discount ?? 0) / 1.15;
            });
            $calculatedNetSubtotal = $calculatedGrossSubtotal - $calculatedTotalDiscount;
            $calculatedTotal = $quote->details->sum(function ($item) {
                return ($item->price * $item->quantity) - ($item->discount ?? 0);
            });
            $calculatedIva = $calculatedTotal - $calculatedNetSubtotal;
            @endphp

            <table class="line-items-container has-bottom-border"
                style="border-collapse: collapse; border:none; page-break-inside: avoid;">
                <thead>
                    <tr style="border:none;">
                        <th style="text-align:right; border:none;">Resumen de Totales</th>
                    </tr>
                </thead>

                <tbody>
                    <tr style="border:none;">
                        <td style="text-align:left; border:none;">
                            <table style="width: 100%; border-collapse: collapse; border:none;">
                                <tr style="border:none;">
                                    <td class=""
                                        style="padding:4px 0; text-align:right; padding-right:15px; width:75%; white-space:nowrap; border:none; color:#d38181; font-weight:700;">
                                        SUBTOTAL (SIN IVA):
                                    </td>
                                    <td class=""
                                        style="padding:4px 0; text-align:right; width:25%; white-space:nowrap; border:none; font-weight:700;">
                                        ${{ number_format($calculatedGrossSubtotal, 2) }}
                                    </td>
                                </tr>

                                @if ($calculatedTotalDiscount > 0)
                                <tr style="border:none;">
                                    <td class=""
                                        style="padding:4px 0; text-align:right; padding-right:15px; white-space:nowrap; border:none; color:#d38181; font-weight:700;">
                                        DESCUENTO:
                                    </td>
                                    <td class=""
                                        style="padding:4px 0; text-align:right; white-space:nowrap; border:none; font-weight:700;">
                                        -${{ number_format($calculatedTotalDiscount, 2) }}
                                    </td>
                                </tr>
                                @endif

                                <tr style="border:none;">
                                    <td class=""
                                        style="padding:4px 0; text-align:right; padding-right:15px; white-space:nowrap; border:none; color:#d38181; font-weight:700;">
                                        IVA (15%):
                                    </td>
                                    <td class=""
                                        style="padding:4px 0; text-align:right; font-weight:bold; white-space:nowrap; border:none;">
                                        ${{ number_format($calculatedIva, 2) }}
                                    </td>
                                </tr>

                                <tr style="border:none;">
                                    <td style="padding-top:10px; text-align:right; padding-right:15px; white-space:nowrap; border:none;">
                                        <span class="total_cancelar">TOTAL COTIZADO:</span>
                                    </td>
                                    <td style="padding-top:10px; text-align:right; white-space:nowrap; border:none;">
                                        <span class="total_cancelar total_cancelar_value">${{ number_format($calculatedTotal, 2) }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="footer" style="clear: both; margin-top: 30px;">
                <div class="footer-info">
                    <span> OBSERVACIONES: {{ $quote->observations ?? 'Sin observaciones' }} </span>
                </div>
            </div>
        </div>

        <footer class="footer_page"
            style="
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                padding: 15px 0;
                text-align: center;
                background: #ffffff; 
                color: #333333; 
                font-size: 11px;
                border-top: 1px solid #ddd;
                letter-spacing: 0.3px;
            ">
            <p style="margin: 3px 0; color: #666666; font-weight: 500; font-size: 10.5px;">
                UBICACIÓN: {{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}
            </p>
            <p style="margin: 6px 0 0 0; font-size: 9.5px; color: #999999;">
                Este presupuesto tiene una validez de 15 días a partir de la fecha de emisión.
                <br>© 2026 <strong>{{ $sucursal->trade_name ?? 'Luxury Evys' }}</strong>. Todos los derechos reservados.
            </p>
        </footer>

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
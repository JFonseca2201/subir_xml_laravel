<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <title> {{ $sale->document_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        /**
        Use a more readable tab size (opinionated).
        */

        :root {
            -moz-tab-size: 4;
            tab-size: 4;
        }

        /**
        1. Correct the line height in all browsers.
        2. Prevent adjustments of font size after orientation changes in iOS.
        */

        html {
            line-height: 1.15;
            /* 1 */
            -webkit-text-size-adjust: 100%;
            /* 2 */
        }

        /*
        Sections
        ========
        */

        /**
        Remove the margin in all browsers.
        */

        body {
            margin: 0;
        }

        /**
        Improve consistency of default fonts in all browsers. (https://github.com/sindresorhus/modern-normalize/issues/3)
        */

        body {
            font-family:
                system-ui,
                -apple-system,
                /* Firefox supports this but not yet `system-ui` */
                'Segoe UI',
                Roboto,
                Helvetica,
                Arial,
                sans-serif,
                'Apple Color Emoji',
                'Segoe UI Emoji';
        }

        /*
        Grouping content
        ================
        */

        /**
        1. Add the correct height in Firefox.
        2. Correct the inheritance of border color in Firefox. (https://bugzilla.mozilla.org/show_bug.cgi?id=190655)
        */

        hr {
            height: 0;
            /* 1 */
            color: inherit;
            /* 2 */
        }

        /*
        Text-level semantics
        ====================
        */

        /**
        Add the correct text decoration in Chrome, Edge, and Safari.
        */

        abbr[title] {
            text-decoration: underline dotted;
        }

        /**
        Add the correct font weight in Edge and Safari.
        */

        b,
        strong {
            font-weight: bolder;
        }

        /**
        1. Improve consistency of default fonts in all browsers. (https://github.com/sindresorhus/modern-normalize/issues/3)
        2. Correct the odd 'em' font sizing in all browsers.
        */

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
            /* 1 */
            font-size: 1em;
            /* 2 */
        }

        /**
        Add the correct font size in all browsers.
        */

        small {
            font-size: 80%;
        }

        /**
        Prevent 'sub' and 'sup' elements from affecting the line height in all browsers.
        */

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

        /*
        Tabular data
        ============
        */

        /**
        1. Remove text indentation from table contents in Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=999088, https://bugs.webkit.org/show_bug.cgi?id=201297)
        2. Correct table border color inheritance in all Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=935729, https://bugs.webkit.org/show_bug.cgi?id=195016)
        */

        table {
            text-indent: 0;
            /* 1 */
            border-color: inherit;
            /* 2 */
        }

        /*
        Forms
        =====
        */

        /**
        1. Change the font styles in all browsers.
        2. Remove the margin in Firefox and Safari.
        */

        button,
        input,
        optgroup,
        select,
        textarea {
            font-family: inherit;
            /* 1 */
            font-size: 100%;
            /* 1 */
            line-height: 1.15;
            /* 1 */
            margin: 0;
            /* 2 */
        }

        /**
        Remove the inheritance of text transform in Edge and Firefox.
        1. Remove the inheritance of text transform in Firefox.
        */

        button,
        select {
            /* 1 */
            text-transform: none;
        }

        /**
        Correct the inability to style clickable types in iOS and Safari.
        */

        button,
        [type='button'],
        [type='reset'],
        [type='submit'] {
            -webkit-appearance: button;
        }

        /**
        Remove the inner border and padding in Firefox.
        */

        ::-moz-focus-inner {
            border-style: none;
            padding: 0;
        }

        /**
        Restore the focus styles unset by the previous rule.
        */

        :-moz-focusring {
            outline: 1px dotted ButtonText;
        }

        /**
        Remove the additional ':invalid' styles in Firefox.
        See: https://github.com/mozilla/gecko-dev/blob/2f9eacd9d3d995c937b4251a5557d95d494c9be1/layout/style/res/forms.css#L728-L737
        */

        :-moz-ui-invalid {
            box-shadow: none;
        }

        /**
        Remove the padding so developers are not caught out when they zero out 'fieldset' elements in all browsers.
        */

        legend {
            padding: 0;
        }

        /**
        Add the correct vertical alignment in Chrome and Firefox.
        */

        progress {
            vertical-align: baseline;
        }

        /**
        Correct the cursor style of increment and decrement buttons in Safari.
        */

        ::-webkit-inner-spin-button,
        ::-webkit-outer-spin-button {
            height: auto;
        }

        /**
        1. Correct the odd appearance in Chrome and Safari.
        2. Correct the outline style in Safari.
        */

        [type='search'] {
            -webkit-appearance: textfield;
            /* 1 */
            outline-offset: -2px;
            /* 2 */
        }

        /**
        Remove the inner padding in Chrome and Safari on macOS.
        */

        ::-webkit-search-decoration {
            -webkit-appearance: none;
        }

        /**
        1. Correct the inability to style clickable types in iOS and Safari.
        2. Change font properties to 'inherit' in Safari.
        */

        ::-webkit-file-upload-button {
            -webkit-appearance: button;
            /* 1 */
            font: inherit;
            /* 2 */
        }

        /*
        Interactive
        ===========
        */

        /*
        Add the correct display in Chrome and Safari.
        */

        summary {
            display: list-item;
        }
    </style>
    <style>
        @page {
            margin: 18mm 15mm 18mm 15mm;
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
            margin: 20px 0 30px 0;
        }

        .invoice-info-container {
            font-size: 0.875em;
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
            font-size: 0.875em;
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
            width: 50px;
        }

        .line-items-container th.heading-item {
            width: 50px;
        }

        .line-items-container th.heading-price {
            text-align: right;
            width: 100px;
        }

        .line-items-container th.heading-subtotal {
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

        .footer-thanks {
            font-size: 1.125em;
        }

        .footer-thanks img {
            display: inline-block;
            position: relative;
            top: 1px;
            width: 16px;
            margin-right: 4px;
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

        .number-clausulas {
            /* display: -webkit-inline-box;
            display: -webkit-box; */
        }

        .number-clausulas p {
            margin: 0;
            text-align: justify;
            font-size: 0.76rem;
            /*0.68rem*/
            ;
        }

        .number-clausulas strong {
            float: left;
            font-size: 0.76rem;
            /*0.68rem*/
            ;
        }

        .number-clausulas ul li {
            font-size: 0.76rem;
            /*0.68rem*/
            ;
        }

        .place-date {
            text-align: right;
        }

        .place-date p {
            /* font-size: small; */
            font-size: 0.6rem;
        }
    </style>
    @if(request()->has('print'))
    <style>
        @page {
            margin: 0 !important;
        }

        /* Modern print preview control bar */
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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

        /* Screen Preview Styling */
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
                box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
                border-radius: 8px !important;
                padding: 60px 45px !important;
                box-sizing: border-box !important;
            }
        }

        /* Printing Styles */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            body {
                display: block !important;
                min-height: auto !important;
                background-color: white !important;
                padding: 25mm 20mm 25mm 20mm !important;
                margin: 0 !important;
            }
            .print-container {
                padding: 0 !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
    @endif
</head>

<body>
    @php
        $sucursal = \App\Models\Config\Sucursale::find($sale->user->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
        $logoSrc = ($sucursal && $sucursal->logo) 
            ? (request()->has('print') ? asset($sucursal->logo) : public_path($sucursal->logo)) 
            : (request()->has('print') ? asset('assets/img/brand/logo.jpeg') : public_path('assets/img/brand/logo.jpeg'));
    @endphp
    @if(request()->has('print'))
    <!-- Action Bar -->     
    <div class="no-print print-preview-bar">
        <div class="print-preview-info">
            <span class="preview-title">Previsualización de {{ $sale->document_type === 'quote' ? 'Cotización' : ($sale->document_type === 'invoice' ? 'Factura' : 'Nota de Venta') }} #{{ $sale->document_number }}</span>
            <span class="preview-subtitle">Revisa el documento antes de imprimir</span>
        </div>
        <div class="print-preview-actions">
            <button onclick="window.print()" class="btn btn-print">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 6px;">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>
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

        <!-- See invoice.html! It is injected here... -->
        <div class="page-container">
            Page
            <span class="page"></span>
            of
            <span class="pages"></span>
        </div>

        <div class="logo-container">
            <table style="width: 100%; border-collapse: collapse; border: none !important;">
                <tbody>
                    <tr style="border: none !important;">
                        <td style="padding: 0 !important; border: none !important;">
                            <img style="height: 80px; border: none !important; outline: none !important;"
                                src="{{ $logoSrc }}">
                        </td>

                        <td style="padding: 0 !important; border: none !important;">
                            <strong>{{ $sale->document_number }}</strong>
                            <br>
                            <img style="width:60px; border: none !important; outline: none !important;"
                                src="{{ request()->has('print') ? asset('assets/img/brand/qr.png') : public_path('assets/img/brand/qr.png') }}">
                            <br>
                            <small>RUC: {{ $sucursal->ruc ?? '1793192550001' }}</small>
                            <br>
                            <small>https://www.luxuryevys.com</small>
                            <br>
                            <small>{{ $sucursal->email ?? 'comp.luxuryevys@gmail.com' }}</small>
                            <br>
                            <small>Telf: {{ $sucursal->phone ?? '0999179988 / 0963089601' }}</small>
                            <br>

                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="clear:both;"></div>
        <table class="invoice-info-container" style="width: 100%; margin-bottom: 10px; border-collapse: collapse; text-transform: uppercase;">
            <tr style="border-bottom: 0.8px solid #949494;">
                <td style="width: 50%; padding-bottom: 6px; font-weight: bold; font-size: 11px; text-align: left; border: none;">
                    <strong>{{ $sale->document_type === 'quote' ? 'COTIZACIÓN' : ($sale->document_type === 'invoice' ? 'FACTURA' : 'NOTA DE VENTA') }} #:</strong> {{ $sale->document_number }}-{{ $sale->id }}
                </td>
                <td style="width: 50%; padding-bottom: 6px; text-align: right; font-weight: bold; font-size: 11px; border: none;">
                    FECHA: {{ $sale->service_date->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td style="width: 48%; vertical-align: top; padding-right: 15px; padding-top: 15px; border: none;">
                    <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">DATOS DEL CLIENTE</div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">NOMBRE:</span> {{ $sale->client->full_name }}
                    </div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">
                            @if ($sale->client->type_document == 1)
                                CI #:
                            @elseif($sale->client->type_document == 2)
                                RUC #:
                            @endif
                        </span> {{ $sale->client->n_document }}
                    </div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">EMAIL:</span> 
                        <span style="text-transform: lowercase;">{{ $sale->client->email ?? 'Sin información' }}</span>
                    </div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">TELÉFONO:</span> {{ $sale->client->phone ?? 'Sin información' }}
                    </div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">DIRECCIÓN:</span> {{ $sale->client->address ?? 'Sin información' }}
                    </div>
                    <div style="margin-bottom: 5px; font-size: 9.5px;">
                        <span style="font-weight: bold;">CIUDAD/PROVINCIA:</span> 
                        @if ($sale->client->ubigeo_provincia || $sale->client->ubigeo_distrito)
                            {{ $sale->client->ubigeo_provincia ?? 'PICHINCHA' }}/{{ $sale->client->ubigeo_distrito ?? 'QUITO' }}
                        @else
                            QUITO/PICHINCHA
                        @endif
                    </div>
                </td>
                <td style="width: 48%; vertical-align: top; padding-left: 15px; padding-top: 15px; border: none; text-align: left;">
                    @if ($sale->vehicle)
                        <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">DATOS DEL VEHÍCULO</div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">PLACA:</span> {{ $sale->vehicle->license_plate ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">MARCA:</span> {{ $sale->vehicle->brand ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">MODELO:</span> {{ $sale->vehicle->brand }} {{ $sale->vehicle->model ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">AÑO:</span> {{ $sale->vehicle->year ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">TIPO:</span> {{ $sale->vehicle->vehicle_type ?? 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">KILOMETRAJE:</span> {{ $sale->mileage ? $sale->mileage . ' km' : 'Sin información' }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">COLOR:</span> {{ $sale->vehicle->color ?? 'Sin información' }}
                        </div>
                    @else
                        <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px;">INFORMACIÓN ADICIONAL</div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">SUCURSAL:</span> {{ $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.') }}
                        </div>
                        <div style="margin-bottom: 5px; font-size: 9.5px;">
                            <span style="font-weight: bold;">DIRECCIÓN:</span> {{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}
                        </div>
                    @endif
                </td>
            </tr>
            <tr style="border-top: 0.8px solid #949494ff;">
                <td style="padding-top: 8px; border: none; font-size: 9.5px; text-align: left;">
                    VENDEDOR: <strong>{{ $sale->user->name }}</strong>
                </td>
                <td style="padding-top: 8px; text-align: right; border: none; font-size: 9.5px;">
                    TELÉFONO: {{ $sale->user->phone ?? '022698134' }}
                </td>
            </tr>
            @if ($sale->technicians && $sale->technicians->count() > 0)
            <tr>
                <td colspan="2" style="padding-top: 4px; border: none; font-size: 9.5px; text-align: left;">
                    TÉCNICO(S):
                    @foreach ($sale->technicians as $technician)
                        <b>{{ $technician->first_name }} {{ $technician->last_name }}</b>@if (!$loop->last), @endif
                    @endforeach
                </td>
            </tr>
            @endif
            @if ($sale->vehicle)
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
                    <th class="heading-item">#</th>
                    <th class="heading-description">Descripción</th>
                    <th class="heading-quantity">Cantidad</th>
                    <th class="heading-price">Precio</th>
                    <th class="heading-price">Descuento</th>
                    <th class="heading-subtotal">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $cont = 0;
                @endphp
                @foreach ($sale->details as $detail)
                    <tr>
                        <td class="center">{{ $cont = $cont + 1 }}</td>
                        <td>
                            {{ $detail->description }}
                            @if ($detail->product?->sku)
                                <br>
                                <small style="color: #666;">SKU: {{ $detail->product->sku }}</small>
                            @endif
                        </td>
                        <td class="right">{{ $detail->quantity }}</td>
                        <td class="right">${{ $detail->price }}</td>
                        <td class="right">${{ $detail->discount ?? 0.0 }}</td>
                        <td class="bold">${{ $detail->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>


        <hr>
        @php
            $payments = collect();

            // Los pagos distribuidos le pertenecen a FinanceRecord, no directamente a Sale.
            // Esta relación ya viene cargada desde SaleController::generateSinglePDF
            if (isset($sale->financeRecord) && $sale->financeRecord->paymentDistributions->count() > 0) {
                $payments = $sale->financeRecord->paymentDistributions;
            } elseif (isset($sale->payments) && $sale->payments->count() > 0) {
                $payments = $sale->payments;
            }

            if ($payments->isEmpty() && $sale->document_type !== 'quote') {
                $payments->push(
                    (object) [
                        'created_at' => $sale->created_at,
                        'payment_method' => $sale->payment_method ?? 'Efectivo',
                        'amount' => $sale->total,
                    ],
                );
            }
            $totalDiscount = $sale->details->sum('discount') ?? 0;
            $grossSubtotal = $sale->details->sum(function ($item) {
                return $item->quantity * $item->price;
            });
        @endphp

        @if ($sale->document_type == 'sale_note' || $sale->document_type == 'invoice')
            <table class="line-items-container has-bottom-border" style="border:none; page-break-inside: avoid;">
                <thead>
                    <tr>
                        <th style="text-align:left;">Fecha Pago</th>
                        <th style="text-align:left;">Método de Pago</th>
                        <th style="text-align:left;">Monto</th>
                        <th style="text-align:left;">Información de Pago</th>
                    </tr>
                </thead>

                <tbody>
                    @if ($payments->isEmpty())
                        <tr style="border:none; vertical-align: top;">
                            <td colspan="3" style="text-align:center; padding:10px 0; vertical-align: top;">No hay
                                pagos registrados</td>
                            <td style="text-align:left; border:none; padding:0; vertical-align: top;">
                                <table style="width:100%; border:none; border-collapse:collapse;">

                                    <tr style="border:none;">
                                        <td class=""
                                            style="padding:4px 0; text-align:right; padding-right:10px; width:83.33%; white-space:nowrap; border:none; color:#d38181;">
                                            SUBTOTAL:
                                        </td>
                                        <td class=""
                                            style="padding:4px 0; text-align:left; width:16.66%; white-space:nowrap; border:none;">
                                            ${{ number_format($grossSubtotal, 2) }}
                                        </td>
                                    </tr>

                                    @if ($totalDiscount > 0)
                                        <tr style="border:none;">
                                            <td class=""
                                                style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none; color:#d38181;">
                                                DESCUENTO:
                                            </td>
                                            <td class=""
                                                style="padding:4px 0; text-align:left; white-space:nowrap; border:none;">
                                                -${{ number_format($totalDiscount, 2) }}
                                            </td>
                                        </tr>
                                    @endif

                                    {{--   <tr style="border:none;">
                                        <td class="large"
                                            style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                            BASE IMPONIBLE:
                                        </td>
                                        <td class="large"
                                            style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                            ${{ number_format($sale->subtotal, 2) }}
                                        </td>
                                    </tr> --}}

                                    @if ($sale->tax_amount > 0)
                                        <tr style="border:none;">
                                            <td class="large"
                                                style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                                IVA (15%):
                                            </td>
                                            <td class="large"
                                                style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                                ${{ number_format($sale->tax_amount, 2) }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr style="border:none;">
                                        <td colspan="1"
                                            style="padding-top:10px; text-align:right; white-space:nowrap; border:none;">
                                            <span class="total_cancelar">TOTAL:</span>
                                        </td>
                                        <td colspan="1"
                                            style="padding-top:10px; text-align:left; white-space:nowrap; border:none;">
                                            <span class="total_cancelar total_cancelar_value">${{ number_format($sale->total, 2) }}</span>
                                        </td>
                                    </tr>

                                </table>
                            </td>
                        </tr>
                    @else
                        @foreach ($payments as $index => $payment)
                            <tr style="border:none; vertical-align: top;">

                                {{-- FECHA --}}
                                <td style="text-align:left; border:none; padding:3px 0; vertical-align: top;">
                                    {{ Carbon\Carbon::parse($payment->created_at)->format('Y/m/d') }}
                                </td>

                                {{-- MÉTODO --}}
                                <td style="text-align:left; border:none; padding:3px 0; vertical-align: top;">
                                    @php
                                        $metodo = 'Efectivo';
                                        if (isset($payment->paymentMethod->name)) {
                                            $metodo = $payment->paymentMethod->name;
                                        } elseif (isset($payment->method_payment)) {
                                            $metodo = $payment->method_payment;
                                        } elseif (isset($payment->payment_method)) {
                                            $metodo = $payment->payment_method;
                                        } elseif (isset($payment->payment_method_id)) {
                                            $pm = \Illuminate\Support\Facades\DB::table('payment_methods')
                                                ->where('id', $payment->payment_method_id)
                                                ->first();
                                            if ($pm) {
                                                $metodo = $pm->name;
                                            }
                                        }
                                    @endphp
                                    {{ $metodo }}
                                </td>

                                {{-- MONTO --}}
                                <td style="text-align:left; border:none; padding:3px 0; vertical-align: top;">
                                    ${{ number_format($payment->amount, 2) }}
                                </td>

                                {{-- INFORMACIÓN DE PAGO SOLO EN LA PRIMERA FILA --}}
                                @if ($index === 0)
                                    <td style="text-align:left; border:none; padding:0; vertical-align: top;"
                                        rowspan="{{ count($payments) }}">
                                        <table style="width:100%; border:none; border-collapse:collapse;">

                                            <tr style="border:none;">
                                                <td class=""
                                                    style="padding:4px 0; text-align:right; padding-right:10px; width:83.33%; white-space:nowrap; border:none; color:#d38181;">
                                                    SUBTOTAL:
                                                </td>
                                                <td class=""
                                                    style="padding:4px 0; text-align:left; width:16.66%; white-space:nowrap; border:none;">
                                                    ${{ number_format($grossSubtotal, 2) }}
                                                </td>
                                            </tr>

                                            @if ($totalDiscount > 0)
                                                <tr style="border:none;">
                                                    <td class=""
                                                        style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none; color:#d38181;">
                                                        DESCUENTO:
                                                    </td>
                                                    <td class=""
                                                        style="padding:4px 0; text-align:left; white-space:nowrap; border:none;">
                                                        -${{ number_format($totalDiscount, 2) }}
                                                    </td>
                                                </tr>
                                            @endif

                                            {{-- <tr style="border:none;">
                                                <td class="large"
                                                    style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                                    BASE IMPONIBLE:
                                                </td>
                                                <td class="large"
                                                    style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                                    ${{ number_format($sale->subtotal, 2) }}
                                                </td>
                                            </tr> --}}

                                            @if ($sale->tax_amount > 0)
                                                <tr style="border:none;">
                                                    <td class="large"
                                                        style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                                        IVA (15%):
                                                    </td>
                                                    <td class="large"
                                                        style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                                        ${{ number_format($sale->tax_amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endif

                                            <tr style="border:none;">
                                                <td class="large"
                                                    style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                                    PAGO TOTAL:
                                                </td>
                                                <td class="large"
                                                    style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                                    ${{ number_format($sale->paid_out ?? $payments->sum('amount'), 2) }}
                                                </td>
                                            </tr>

                                            <tr style="border:none;">
                                                <td class="large"
                                                    style="padding:4px 0; text-align:right; padding-right:10px; white-space:nowrap; border:none;">
                                                    SALDO:
                                                </td>
                                                <td class="large"
                                                    style="padding:4px 0; text-align:left; font-weight:bold; white-space:nowrap; border:none;">
                                                    ${{ number_format($sale->debt ?? $sale->total - $payments->sum('amount'), 2) }}
                                                </td>
                                            </tr>

                                            <tr style="border:none;">
                                                <td colspan="1"
                                                    style="padding-top:10px; text-align:right; white-space:nowrap; border:none;">
                                                    <span class="total_cancelar">TOTAL:</span>
                                                </td>
                                                <td colspan="1"
                                                    style="padding-top:10px; text-align:left; white-space:nowrap; border:none;">
                                                    <span
                                                        class="total_cancelar total_cancelar_value">${{ number_format($sale->total, 2) }}</span>
                                                </td>
                                            </tr>

                                        </table>
                                    </td>
                                @endif

                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        @elseif ($sale->document_type == 'quote')
            <table class="line-items-container has-bottom-border"
                style="border-collapse: collapse; border:none; page-break-inside: avoid;">
                <thead>
                    <tr style="border:none;">
                        <th style="text-align:right; border:none;">Información de Pago</th>
                    </tr>
                </thead>

                <tbody>
                    <tr style="border:none;">
                        <td style="text-align:left; border:none;">

                            <table style="width: 100%; border-collapse: collapse; border:none;">

                                <tr style="border:none;">
                                    <td class=""
                                        style="padding:4px 0; text-align:right; padding-right:10px; width:83.33%; white-space:nowrap; border:none; color:#d38181; font-weight:700;">
                                        SUBTOTAL:
                                    </td>
                                    <td class=""
                                        style="padding:4px 0; text-align:left; width:16.66%; white-space:nowrap; border:none;">
                                        ${{ number_format($grossSubtotal, 2) }}
                                    </td>
                                </tr>

                                @if ($totalDiscount > 0)
                                    <tr style="border:none;">
                                        <td class=""
                                            style="padding:4px 0; text-align:right; padding-right:10px; width:83.33%; white-space:nowrap; border:none; color:#d38181; font-weight:700;">
                                            DESCUENTO:
                                        </td>
                                        <td class=""
                                            style="padding:4px 0; text-align:left; width:16.66%; white-space:nowrap; border:none;">
                                            -${{ number_format($totalDiscount, 2) }}
                                        </td>
                                    </tr>
                                @endif

                                @if ($sale->tax_amount > 0)
                                    <tr style="border:none;">
                                        <td class="large"
                                            style="padding:4px 0; text-align:right; padding-right:10px; width:83.33%; white-space:nowrap; border:none;">
                                            IVA (15%):
                                        </td>
                                        <td class="large"
                                            style="padding:4px 0; text-align:left; width:16.66%; font-weight:bold; white-space:nowrap; border:none;">
                                            ${{ number_format($sale->tax_amount, 2) }}
                                        </td>
                                    </tr>
                                @endif

                                <tr style="border:none;">
                                    <td colspan="1"
                                        style="padding-top:10px; text-align:right; width:83.33%; white-space:nowrap; border:none;">
                                        <span class="total_cancelar">
                                            TOTAL:
                                        </span>
                                    </td>
                                    <td colspan="1"
                                        style="padding-top:10px; text-align:left; width:45.66%; white-space:nowrap; border:none;">
                                        <span class="total_cancelar total_cancelar_value">
                                            ${{ number_format($sale->total, 2) }}
                                        </span>
                                    </td>
                                </tr>

                            </table>

                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        <div class="footer">
            <div class="footer-info">
                <span> ANOTACIONES FINALES: {{ $sale->observations ?? 'Sin observaciones' }} </span>
            </div>
        </div>
    </div>

    <footer class="footer_page"
        style="
        position: absolute;
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
            © 2026 <strong>{{ $sucursal->trade_name ?? 'Luxury Evys' }}</strong>. Todos los derechos reservados.
        </p>
    </footer>

    @if(request()->has('print'))
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 600);
        });
    </script>
    @endif
</body>

</html>

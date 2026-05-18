<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <title> {{ $sale->document_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
        body {
            font-size: 13px;
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
</head>

<body>

    <div class="web-container page-break">

        <!-- See invoice.html! It is injected here... -->
        <div class="page-container">
            Page
            <span class="page"></span>
            of
            <span class="pages"></span>
        </div>

        <div class="logo-container">
            <table>
                <tbody>
                    <tr>
                        <td style="padding: 0 !important;border-bottom:none;">
                            <img
                                style="height: 75px;background:black;"
                                src="{{ public_path('logo.png') }}">
                        </td>

                        <td style="padding: 0 !important;border-bottom:none;">
                            <strong>{{ $sale->document_number }}</strong>
                            <br>
                            <img
                                style="width:130px;background:black;"
                                src="{{ public_path('logo.png') }}">
                            <br>
                            <small>RUC: 1793192550001</small>
                            <br>
                            <small>https://www.luxuryevys.com</small>
                            <br>
                            <small>comp.luxuryevys@gmail.com</small>
                            <br>
                            <small>Telf: 0999179988 / 0963089601</small>
                            <br>
                           
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="clear:both;"></div>
        <table class="invoice-info-container">
            <tr>
                <td>
                    <strong>{{ $sale->document_number }}-{{ $sale->id }}</strong>
                </td>

                <td>
                    FECHA: {{ $sale->service_date->format('d/m/Y') }}
                </td>
            </tr>
            <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
            <tr>
                <td class="">
                    <h3 style="margin:0;">DATOS DEL CLIENTE: </h3>
                    <br>
                    <br>
                    <b>NOMBRE:</b> {{ $sale->client->full_name }}
                    <br>
                    <br>
                    <b style="display: inline-block;">EMAIL:</b>
                    <span style="display: inline-block;text-transform: lowercase;vertical-align: middle;">{{ $sale->client->email ?? 'Sin información' }}</span>
                    <br>
                    <br>
                    <b>DIRECCIÓN:</b> {{ $sale->client->address ?? 'Sin información' }}
                </td>
                <td>
                    <table style="width:100%;">
                        <br><br>
                        <tr>
                            <td style="border:none;padding:0;">
                                <strong>
                                    @if($sale->client->type_document == 1)
                                    CI #:
                                    @elseif($sale->client->type_document == 2)
                                    RUC #:
                                    @endif
                                </strong>
                            </td>
                            <td style="text-align: left;"> {{ $sale->client->n_document }}</td>
                        </tr>
                        <tr>
                            <td><b>CIUDAD/PROVINCIA:</b></td>
                            <td style="text-align: left;">
                                @if($sale->client->ubigeo_provincia || $sale->client->ubigeo_distrito)
                                <strong>{{ $sale->client->ubigeo_provincia ?? 'Sin información'}}/{{ $sale->client->ubigeo_distrito ?? 'Sin información' }}</strong>
                                @else
                                Sin información
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><b>TELÉFONO:</b></td>
                            <td style="text-align: left;">{{ $sale->client->phone ?? 'Sin información' }}</td>
                        </tr>
                        <tr>
                            <td><b>TIPO CLIENTE:</b></td>
                            <td style="text-align: left;">{{ $sale->client->type_client == 1 ? 'NATURAL' : 'JURIDICO' }}</td>
                        </tr>

                    </table>
                </td>
            </tr>

            <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
            <tr>
                <td>

                    <br>
                    SUCURSAL DE ATENCION: <strong>LUXURY EVYS CIA. LTDA.</strong>
                    <br>DIRECCIÓN: <strong>SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C</strong>
                    <br>
                </td>
                <td></td>
            </tr>
            <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
            @if($sale->vehicle)
            <tr>
                <td class="" style="text-transform: uppercase;">
                    <h3 style="margin:0;">DATOS DEL VEHÍCULO: </h3>
                    <br>
                    <br>
                    <b>PLACA:</b> {{ $sale->vehicle->license_plate ?? 'Sin información' }}
                    <br>
                    <br>
                    <b>MARCA:</b> {{ $sale->vehicle->brand ?? 'Sin información' }}
                    <br>
                    <br>
                    <b>MODELO:</b> {{ $sale->vehicle->model ?? 'Sin información' }}
                    <br>
                    <br>
                    <b>AÑO:</b> {{ $sale->vehicle->year ?? 'Sin información' }}
                    <br>
                </td>
                <td>
                    <table style="width:100%; text-transform: uppercase;">

                        <tr>
                            <td style="border:none;padding:0;">
                                <strong>
                                    <b>TIPO:</b>
                                </strong>
                            </td>
                            <td style="text-align: left;"> {{ $sale->vehicle->vehicle_type ?? 'Sin información' }}</td>
                        </tr>

                        <tr>
                            <td><b>KILOMETRAJE:</b></td>
                            <td style="text-align: left;">{{ $sale->vehicle->mileage ?? 'Sin información' }}</td>
                        </tr>
                        <tr>
                            <td><b>COLOR:</b></td>
                            <td style="text-align: left;">{{ $sale->vehicle->color ?? 'Sin información' }}</td>
                        </tr>

                    </table>
                </td>
            </tr>
            <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
            @endif
            <tr>
                <td>
                    VENDEDOR: <strong>{{ $sale->user->name }}</strong>
                </td>
                <td>
                    TELÉFONO: {{ $sale->user->phone?? '022698134' }}
                </td>
            </tr>

        </table>

        <table class="line-items-container">
            <thead>
                <tr>
                    <th class="heading-item">#</th>
                    <th class="heading-description">Descripción</th>
                    <th class="heading-quantity">Cant.</th>
                    <th class="heading-price">Precio</th>
                    <th class="heading-price">Descuento</th>
                    <th class="heading-subtotal">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $detail)
                <tr>
                    <td class="center">{{ $detail->id }}</td>
                    <td>

                        {{ $detail->description }}
                    </td>
                    <td class="right">{{ $detail->quantity }}</td>
                    <td class="right">${{ $detail->price }}</td>
                    <td class="right">${{ $detail->discount ?? 0.00 }}</td>
                    <td class="bold">${{ $detail->total }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>


        <table class="line-items-container has-bottom-border">
            <thead>
                <tr>
                    <th>Metodo de Pago</th>
                    <th>Fecha Entrega</th>
                    <th>Información de Pago</th>
                </tr>
            </thead>
            <tbody>
                @if($sale->financeRecord && $sale->financeRecord->paymentDistributions->count() > 0)
                @foreach($sale->financeRecord->paymentDistributions as $distribution)
                <tr>
                    <td class="payment-info">
                        <div>
                            METODO DE PAGO: <strong>{{ strtoupper($distribution->payment_method) }}</strong>
                            @if($distribution->account)
                            <br>
                            CUENTA: {{ $distribution->account->name ?? '' }}
                            @endif
                        </div>
                    </td>
                    <td class="payment-info">{{ $sale->service_date ? $sale->service_date->format('Y/m/d') : '' }}</td>

                    <td class="payment-info">
                        <div class="large total">
                            {{ number_format($distribution->amount, 2) }}
                        </div>
                    </td>
                </tr>
                @endforeach
                @else
                <tr>
                    <td class="payment-info">
                        <div>
                            METODO DE PAGO: <strong>{{ strtoupper($sale->payment_method) }}</strong>
                        </div>
                        <div>
                            ESTADO PAGO: <strong>{{ strtoupper($sale->payment_status) }}</strong>
                        </div>
                    </td>
                    <td class="payment-info">{{ $sale->service_date ? $sale->service_date->format('Y/m/d') : '' }}</td>

                    <td class="payment-info">
                        <div class="large total">
                            SUBTOTAL: ${{ number_format($sale->subtotal, 2) }}
                            <br>
                            IGV: ${{ number_format($sale->tax_amount, 2) }}
                        </div>
                        <div class="large total">
                            TOTAL: ${{ number_format($sale->total, 2) }}
                        </div>
                        @if($sale->is_credited)
                        <div>
                            A CRÉDITO: <strong>SI</strong>
                        </div>
                        @endif
                    </td>
                </tr>
                @endif

                @if($sale->financeRecord && $sale->financeRecord->paymentDistributions->count() > 0)
                <tr>
                    <td></td>
                    <td></td>
                    <td class="payment-info">
                        <div class="large total">
                            SUBTOTAL: ${{ number_format($sale->subtotal, 2) }}
                            <br>
                            IGV: ${{ number_format($sale->tax_amount, 2) }}
                        </div>
                        <div class="large total">
                            TOTAL: ${{ number_format($sale->total, 2) }}
                        </div>
                        <div>
                            PAGADO: <strong>${{ number_format($sale->financeRecord->paymentDistributions->sum('amount'), 2) }}</strong>
                        </div>
                        <div>
                            SALDO: <strong>${{ number_format($sale->total - $sale->financeRecord->paymentDistributions->sum('amount'), 2) }}</strong>
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
        <div class="footer">
            <div class="footer-info">
                <span> ANOTACIONES FINALES: {{ $sale->observations ?? 'Sin observaciones' }} </span>
            </div>
        </div>
    </div>
</body>
</html>
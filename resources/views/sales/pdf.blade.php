<!doctype html>
<html class="no-js" lang="">

<head>
  <meta charset="utf-8">
  <title>Nota de Venta #{{ str_pad($sale->id, 8, '0', STR_PAD_LEFT) }}</title>
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
            line-height: 1.15; /* 1 */
            -webkit-text-size-adjust: 100%; /* 2 */
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
                -apple-system, /* Firefox supports this but not yet `system-ui` */
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
            height: 0; /* 1 */
            color: inherit; /* 2 */
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
                monospace; /* 1 */
            font-size: 1em; /* 2 */
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
            text-indent: 0; /* 1 */
            border-color: inherit; /* 2 */
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
            font-family: inherit; /* 1 */
            font-size: 100%; /* 1 */
            line-height: 1.15; /* 1 */
            margin: 0; /* 2 */
        }

        /**
        Remove the inheritance of text transform in Edge and Firefox.
        1. Remove the inheritance of text transform in Firefox.
        */

        button,
        select { /* 1 */
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
            -webkit-appearance: textfield; /* 1 */
            outline-offset: -2px; /* 2 */
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
            -webkit-appearance: button; /* 1 */
            font: inherit; /* 2 */
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

        /* .line-items-container tbody tr:first-child td {
        padding-top: 10px;
        } */

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

        .number-clausulas{
            /* display: -webkit-inline-box;
            display: -webkit-box; */
        }
        .number-clausulas p{
            margin: 0;
            text-align: justify;
            font-size: 0.76rem; /*0.68rem*/;
        }
        .number-clausulas strong{
            float: left;
            font-size: 0.76rem; /*0.68rem*/;
        }
        .number-clausulas ul li{
            font-size: 0.76rem; /*0.68rem*/;
        }
        .place-date{
            text-align: right;
        }
        .place-date p{
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
                    <div style="width: 100px; height: 75px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px; text-align: center;">
                        LUXURY<br>EVYS
                    </div>
                </td>

                <td style="padding: 0 !important;border-bottom:none;">
                    N° SERIE: <strong>#{{ str_pad($sale->id, 8, '0', STR_PAD_LEFT) }}</strong>
                    <br>
                    <small>www.luxuryevys.net</small>
                    <br>
                    <small>info@luxuryevys.net</small>
                    <br>
                    <small>+593 2 123-4567</small>
                </td>
            </tr>
        </tbody>
    </table>
  </div>
  <div style="clear:both;"></div>
  <table class="invoice-info-container">
    <tr>
        <td>
          N° VENTA: <strong>#{{ str_pad($sale->id, 8, '0', STR_PAD_LEFT) }}</strong>
        </td>

        <td>
          FECHA VENTA: {{ date('d/m/Y', strtotime($sale->service_date ?? $sale->created_at)) }}
        </td>
    </tr>
    <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
    <tr>
      <td class="">
        <b>Datos del cliente: </b>
        <br>
        <br>
        CLIENTE: {{ $sale->client ? $sale->client->full_name : '' }}
            <br>
            DEPART./PROVINCIA CLIENTE: <strong>{{ $sale->client ? ($sale->client->region ?? '') . '/' . ($sale->client->provincia ?? '') . '/' . ($sale->client->distrito ?? '') : '' }}</strong>
      </td>
      <td>
        DOC.  {{ $sale->client ? $sale->client->n_document : '' }}
      </td>
    </tr>
    <tr>
        <td>TIPO CLIENTE: {{ $sale->client ? ($sale->client->type_client ?? '') : '' }}</td>
        <td>TELÉFONO: {{ $sale->client ? $sale->client->phone : '' }}</td>
    </tr>
    <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
    <tr>
        <td>
            DIRECCIÓN: <strong>{{ $sale->client ? $sale->client->address : '' }}</strong>
            <br>
            SUCURSAL DE ATENCION: <strong>{{ $sale->client && $sale->client->sucursal ? $sale->client->sucursal->name : '' }}</strong>
        </td>
        <td></td>
    </tr>
    <div class="" style="display: block;width:100%;border:1px solid black;height:1px;"></div>
    <tr>
        <td>
         VENDEDOR: <strong>{{ $sale->user ? $sale->user->name . ' ' . $sale->user->surname : '' }}</strong>
        </td>
        <td>
            TELÉFONO: {{ $sale->user ? $sale->user->phone : '' }}
        </td>
    </tr>

  </table>

  <table class="line-items-container">
    <thead>
      <tr>
        <th class="heading-quantity">Qty</th>
        <th class="heading-description">Descripción</th>
        <th class="heading-price">Subtotal</th>
        <th class="heading-subtotal">Total</th>
      </tr>
    </thead>
    <tbody>
        @foreach($sale->details as $detail)
        <tr>
            <td>{{ $detail->quantity }}</td>
            <td>
                {{ $detail->description }}
                @if($detail->product_id)
                <br>ID Producto: {{ $detail->product_id }}
                @endif
            </td>
            <td class="right">{{ number_format($detail->price, 2) }} PEN</td>
            <td class="bold">{{ number_format($detail->total, 2) }} PEN</td>
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
      <tr>
        <td class="payment-info">
            <div>
                METODO DE PAGO: <strong>{{ strtoupper($sale->payment_method) }}</strong>
            </div>
            <div>
                ESTADO PAGO: <strong>{{ strtoupper($sale->payment_status) }}</strong>
            </div>
        </td>
        <td class="large">{{ $sale->service_date ? $sale->service_date->format('Y/m/d') : '' }}</td>

        <td class="payment-info">
            <div class="large total">
                SUBTOTAL: {{ number_format($sale->subtotal, 2) }} PEN
                <br>
                IGV: {{ number_format($sale->tax_amount, 2) }} PEN
            </div>
            <div class="large total">
                TOTAL: {{ number_format($sale->total, 2) }} PEN
            </div>
            @if($sale->is_credited)
            <div>
              A CRÉDITO: <strong>SI</strong>
            </div>
            @endif
        </td>
    </tbody>
  </table>

    <div class="footer">
        <div class="footer-info">
            <span> ANOTACIONES FINALES: {{ $sale->observations ?? '' }}</span>
        </div>
    </div>

</div>


</body></html>

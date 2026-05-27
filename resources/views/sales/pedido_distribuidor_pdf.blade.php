<!doctype html>
<html class="no-js" lang="es">

<head>
    <meta charset="utf-8">
    <title>Pedido a Distribuidor #{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-indent: 0;
            border-color: inherit;
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

        .logo-container {
            margin: 20px 0 30px 0;
        }

        .invoice-info-container {
            font-size: 0.875em;
        }

        .invoice-info-container td {
            padding: 4px 0;
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
            border-bottom: 1px solid #eee;
        }

        .line-items-container tbody tr:first-child td {
            padding-top: 10px;
        }

        .line-items-container.has-bottom-border tbody tr:last-child td {
            padding-bottom: 25px;
            border-bottom: 2px solid #ddd;
        }

        .line-items-container th.heading-quantity {
            width: 100px;
            text-align: right;
        }

        .line-items-container th.heading-item {
            width: 50px;
            text-align: center;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #999;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .divider {
            display: block;
            width: 100%;
            border: 1px solid black;
            height: 1px;
            margin: 10px 0;
        }
    </style>
</head>

<body>

    <div class="web-container" style="padding-bottom: 90px; padding-left: 20px; padding-right: 20px;">

        <div class="logo-container">
            <table>
                <tbody>
                    <tr>
                        <td style="padding: 0 !important; border-bottom: none; text-align: left;">
                            <img style="height: 125px; background: black;"
                                src="{{ public_path('assets/img/brand/logo.jpeg') }}">
                        </td>

                        <td style="padding: 0 !important; border-bottom: none; text-align: right; line-height: 1.4;">
                            <strong style="font-size: 1.3em; color: #fb7578;">PEDIDO A DISTRIBUIDOR</strong>
                            <br>
                            <span style="font-weight: bold;">#{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</span>
                            <br>
                            <small>RUC: 1793192550001</small>
                            <br>
                            <small>https://www.luxuryevys.com</small>
                            <br>
                            <small>comp.luxuryevys@gmail.com</small>
                            <br>
                            <small>Telf: 0999179988 / 0963089601</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="clear: both;"></div>

        <table class="invoice-info-container">
            <tr>
                <td style="text-align: left;">
                    <strong>PEDIDO #{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</strong>
                </td>

                <td style="text-align: right;">
                    FECHA: {{ \Carbon\Carbon::parse($pedido->created_at)->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="invoice-info-container">
            <tr>
                <td style="text-align: left; vertical-align: top;">
                    <h3 style="margin: 0; font-size: 1.1em; color: #444;">DATOS DEL DISTRIBUIDOR</h3>
                    <br>
                    <b>NOMBRE:</b> {{ $pedido->distribuidor ? $pedido->distribuidor->name : 'N/A' }}
                    @if ($pedido->distribuidor && $pedido->distribuidor->ruc)
                        <br><br>
                        <b>RUC:</b> {{ $pedido->distribuidor->ruc }}
                    @endif
                    @if ($pedido->distribuidor && $pedido->distribuidor->address)
                        <br><br>
                        <b>DIRECCIÓN:</b> {{ $pedido->distribuidor->address }}
                    @endif
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <h3 style="margin: 0; font-size: 1.1em; color: #444;">DATOS DE ENTREGA</h3>
                    <br>
                    <table style="width: 100%;">
                        <tr>
                            <td style="text-align: left;"><b>SUCURSAL:</b></td>
                            <td style="text-align: right;">LUXURY EVYS CIA. LTDA.</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;"><b>DIRECCIÓN:</b></td>
                            <td style="text-align: right;">SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="line-items-container" style="text-transform: uppercase;">
            <thead>
                <tr>
                    <th class="heading-item">#</th>
                    <th class="heading-description" style="text-align: left;">Descripción / Producto</th>
                    <th class="heading-quantity" style="text-align: right;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $cont = 0;
                    $total_items = 0;
                @endphp
                @foreach ($pedido->detalles as $detail)
                    @php
                        $total_items += $detail->cantidad;
                    @endphp
                    <tr>
                        <td class="center" style="text-align: center;">{{ $cont = $cont + 1 }}</td>
                        <td style="text-align: left;">
                            <span
                                style="font-size: 1.1em; color: #444; font-weight: normal;">{{ $detail->description }}</span>
                            @if ($detail->producto && $detail->producto->sku)
                                <br><span style="font-size: 0.85em; color: #777;">SKU:
                                    {{ $detail->producto->sku }}</span>
                            @endif
                        </td>
                        <td class="right"
                            style="text-align: right; font-weight: normal; font-size: 1.1em; color: #444;">
                            {{ $detail->cantidad }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"
                        style="text-align: right; font-weight: bold; font-size: 1.3em; padding-top: 25px; padding-right: 15px; color: #333;">
                        TOTAL DE ITEMS:</td>
                    <td
                        style="text-align: right; font-weight: bold; font-size: 1.6em; padding-top: 25px; color: #fb7578;">
                        {{ $total_items }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($pedido->observations)
            <div
                style="margin-top: 20px; font-size: 0.85em; background-color: #f9f9f9; padding: 10px; border-left: 3px solid #fb7578;">
                <strong>Observaciones:</strong> {{ $pedido->observations }}
            </div>
        @endif

        <div class="footer">
            <p>Documento de control interno generado automáticamente Luxury Evys Cia. Ltda.</p>
        </div>
    </div>
</body>

</html>

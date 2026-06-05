<!doctype html>
<html class="no-js" lang="es">

<head>
    <meta charset="utf-8">
    <title>Pedido a Distribuidor #{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</title>
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

        @page {
            margin: 18mm 15mm 18mm 15mm;
        }

        body {
            margin: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
        }
        a {
            color: inherit !important;
            text-decoration: none !important;
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
        $sucursal = \App\Models\Config\Sucursale::find($pedido->usuario->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
        $logoSrc = ($sucursal && $sucursal->logo) 
            ? (request()->has('print') ? asset($sucursal->logo) : public_path($sucursal->logo)) 
            : (request()->has('print') ? asset('assets/img/brand/logo.jpeg') : public_path('assets/img/brand/logo.jpeg'));
    @endphp
    @if(request()->has('print'))
    <!-- Action Bar -->
    <div class="no-print print-preview-bar">
        <div class="print-preview-info">
            <span class="preview-title">Previsualización de Pedido a Distribuidor #{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</span>
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

    <div class="web-container" style="padding-bottom: 90px; padding-left: 20px; padding-right: 20px;">

        <div class="logo-container">
            <table>
                <tbody>
                    <tr>
                        <td style="padding: 0 !important; border-bottom: none; text-align: left;">
                            <img style="height: 125px; border: none !important; outline: none !important;"
                                src="{{ $logoSrc }}">
                        </td>

                        <td style="padding: 0 !important; border-bottom: none; text-align: right; line-height: 1.4;">
                            <strong style="font-size: 1.3em; color: #fb7578;">PEDIDO A DISTRIBUIDOR</strong>
                            <br>
                            <span style="font-weight: bold;">#{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</span>
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
                            <td style="text-align: right;">{{ $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.') }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;"><b>DIRECCIÓN:</b></td>
                            <td style="text-align: right;">{{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}</td>
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
            <p>Documento de control interno generado automáticamente {{ $sucursal->trade_name ?? 'Luxury Evys Cia. Ltda.' }}</p>
        </div>
    </div>

    @if(request()->has('print'))
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

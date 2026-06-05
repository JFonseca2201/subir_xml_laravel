<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Orden de Trabajo {{ str_replace('OT-', '', $workOrder->number) }}</title>
    <style>
        @page {
            margin: 18mm 15mm 18mm 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            color: #333;
        }
        a {
            color: inherit !important;
            text-decoration: none !important;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .order-number {
            font-size: 14px;
            font-weight: bold;
            color: #d32f2f;
        }

        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .info-column {
            width: 48%;
        }

        .info-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
            background-color: #f5f5f5;
            padding: 5px;
        }

        .info-row {
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
        }

        .items-section {
            margin-bottom: 20px;
        }

        .items-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
            background-color: #f5f5f5;
            padding: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
            font-weight: bold;
            border-bottom: 1px solid #555555ff;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .totals-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .observations-section {
            width: 48%;
            margin-bottom: 20px;
        }

        .observations-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
            background-color: #f5f5f5;
            padding: 5px;
        }

        .observations-text {
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 60px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            padding: 20px;
            border-top: 2px solid #333;
            margin-top: 30px;
        }

        .footer-column {
            width: 48%;
        }

        .signature-line {
            border-top: 1px solid #bbb;
            margin: 60px auto 0 auto;
            width: 180px;
            padding-top: 5px;
            text-align: center;
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
        $sucursal = \App\Models\Config\Sucursale::find($workOrder->user->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
        $logoSrc = ($sucursal && $sucursal->logo) 
            ? (request()->has('print') ? asset($sucursal->logo) : public_path($sucursal->logo)) 
            : (request()->has('print') ? asset('assets/img/brand/logo.jpeg') : public_path('assets/img/brand/logo.jpeg'));
    @endphp
    @if(request()->has('print'))
    <!-- Action Bar -->
    <div class="no-print print-preview-bar">
        <div class="print-preview-info">
            <span class="preview-title">Previsualización de Orden de Trabajo #{{ str_replace('OT-', '', $workOrder->number) }}</span>
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

    <!-- Encabezado -->
    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse; border: none !important;">
        <tr style="border: none !important;">
            <td style="width: 20%; vertical-align: middle; text-align: center; border: none !important;">
                <img style="height: 60px; border: none !important; outline: none !important;" src="{{ $logoSrc }}">
            </td>
            <td style="width: 60%; vertical-align: middle; text-align: center; font-weight: bold; border: none !important;">
                ORDEN DE TRABAJO
            </td>
            <td style="width: 40%; vertical-align: middle; text-align: center; white-space: nowrap; border: none !important;">
                <div style="font-size: 13px; font-weight: bold; color: #d11f008e; white-space: nowrap;">
                    #{{ str_pad(str_replace('OT-', '', $workOrder->number), 6, '0', STR_PAD_LEFT) }}</div>

            </td>
        </tr>
    </table>

    <!-- Información del vehículo y cliente -->
    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse; text-transform: uppercase;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 20px;">
                <div class="info-title">DATOS DEL VEHÍCULO</div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span>
                    {{ $workOrder->created_at ? date('d/m/Y', strtotime($workOrder->created_at)) : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Placa:</span>
                    {{ $workOrder->vehicle ? $workOrder->vehicle->license_plate : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Modelo:</span>
                    {{ $workOrder->vehicle ? $workOrder->vehicle->brand . ' ' . $workOrder->vehicle->model : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Kilometraje:</span>
                    {{ $workOrder->mileage ? $workOrder->mileage . ' km' : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Tipo:</span>
                    {{ $workOrder->vehicle ? $workOrder->vehicle->vehicle_type : 'N/A' }}
                </div>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 20px;">
                <div class="info-title">DATOS DEL CLIENTE</div>
                <div class="info-row">
                    <span class="info-label">Cédula/RUC:</span>
                    {{ $workOrder->client ? $workOrder->client->n_document : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Nombre:</span>
                    {{ $workOrder->client ? $workOrder->client->full_name : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Dirección:</span>
                    {{ $workOrder->client ? $workOrder->client->address : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Teléfono:</span>
                    {{ $workOrder->client ? $workOrder->client->phone : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Correo:</span>
                    {{ $workOrder->client ? $workOrder->client->email : 'N/A' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Lista de trabajos -->
    <div class="items-section">
        <div class="items-title">TRABAJOS REALIZADOS</div>
        <table style="text-transform: uppercase;">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Descuento</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workOrder->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->unit_price, 2) }}</td>
                        <td>${{ number_format($item->discount, 2) }}</td>
                        <td>${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Totales y observaciones -->
    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 20px;">
                <div class="observations-title">OBSERVACIONES</div>
                <div class="observations-text" style="text-transform: uppercase;">
                    {{ $workOrder->observations ?? 'Sin observaciones' }}
                </div>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 20px;">
                <div class="info-title">RESUMEN</div>
                <table style="width: 100%; border-collapse: collapse; border: none !important; margin-top: 5px;">
                    <tr style="border: none !important;">
                        <td style="text-align: left; padding: 4px 0 !important; border: none !important; font-weight: bold;">Subtotal:</td>
                        <td style="text-align: right; padding: 4px 0 !important; border: none !important;">${{ number_format($grossSubtotal, 2) }}</td>
                    </tr>
                    <tr style="border: none !important;">
                        <td style="text-align: left; padding: 4px 0 !important; border: none !important; font-weight: bold;">Descuentos:</td>
                        <td style="text-align: right; padding: 4px 0 !important; border: none !important;">${{ number_format($totalDiscount, 2) }}</td>
                    </tr>
                    <tr style="font-size: 14px; font-weight: bold; border: none !important;">
                        <td style="text-align: left; padding: 8px 0 !important; border: none !important; border-top: 0.8px solid #8a8888ff !important;">TOTAL:</td>
                        <td style="text-align: right; padding: 8px 0 !important; border: none !important; border-top: 0.8px solid #8a8888ff !important; color: #d11f008e;">${{ number_format($total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Pie de página -->
    <table style="width: 100%; margin-top: 10px; border-top: 1px solid #eaeaea; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-top: 20px; text-align: center;">
                <div class="signature-line">
                    <strong>{{ $workOrder->client ? $workOrder->client->full_name : 'Cliente' }}</strong>
                    <br>{{ $workOrder->client ? $workOrder->client->n_document : 'S/N' }}
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-top: 20px; text-align: center;">
                <div class="signature-line">
                    @if ($workOrder->technicians && $workOrder->technicians->count() > 0)
                        @foreach ($workOrder->technicians as $technician)
                            {{ $technician->first_name }} {{ $technician->last_name }}@if (!$loop->last)
                                {{ ', ' }}
                            @endif
                        @endforeach
                    @else
                        Técnico
                    @endif
                    <br>Técnico(s)
                </div>
            </td>            
        </tr>
    </table>

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

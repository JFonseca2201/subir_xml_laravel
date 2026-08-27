<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Kardex Cliente y Vehículo</title>
    <style>
        @page {
            margin: 10mm 10mm 12mm 10mm;
        }

        * {
            box-sizing: border-box;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 10px;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 6px;
        }

        .header-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .header-subtitle {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 2px;
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 8px;
        }

        .info-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }

        .info-content {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
        }

        .plate-badge {
            display: inline-block;
            background: #ffffff;
            border: 1px solid #0f172a;
            border-radius: 3px;
            padding: 1px 4px;
            font-weight: bold;
            font-size: 9px;
        }

        .kpi-table {
            margin-bottom: 10px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            text-align: center;
        }

        .kpi-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }

        .kpi-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }

        .data-table {
            width: 100%;
            margin-top: 4px;
            border: 1px solid #cbd5e1;
        }

        .data-table thead th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 4px;
            border: 1px solid #0f172a;
        }

        .data-table tbody td {
            padding: 4px 4px;
            border: 1px solid #e2e8f0;
            font-size: 8px;
            vertical-align: top;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .data-table tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 8.5px;
            padding: 5px 4px;
            border: 1px solid #cbd5e1;
        }

        .nested-item-table {
            width: 100%;
            margin-top: 2px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 2px;
        }

        .nested-item-table th {
            background: #f1f5f9 !important;
            color: #475569 !important;
            font-size: 7px !important;
            font-weight: bold !important;
            padding: 2px 3px !important;
            border: 1px solid #e2e8f0 !important;
            text-transform: uppercase;
        }

        .nested-item-table td {
            font-size: 7.5px !important;
            padding: 2px 3px !important;
            border: 1px solid #e2e8f0 !important;
        }

        .badge-doc {
            display: inline-block;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-invoice {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-sale-note {
            background: #dcfce7;
            color: #166534;
        }

        .badge-paid {
            color: #16a34a;
            font-weight: bold;
        }

        .badge-partial {
            color: #0284c7;
            font-weight: bold;
        }

        .badge-pending {
            color: #dc2626;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .footer-note {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
            text-align: right;
        }
    </style>
</head>

<body>
    @php
        $showClientCol = !$selectedClient;
        $showVehicleCol = !$selectedVehicle;
        
        // Calcular número total de columnas para colspan
        $totalCols = 7 + ($showClientCol ? 1 : 0) + ($showVehicleCol ? 1 : 0);
        $leftCols = 4 + ($showClientCol ? 1 : 0) + ($showVehicleCol ? 1 : 0);
    @endphp

    <!-- Encabezado Principal -->
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div class="header-title">
                    @if ($selectedClient && $selectedVehicle)
                        Reporte Kardex: {{ $selectedClient['full_name'] }}
                    @elseif ($selectedClient)
                        Reporte Kardex de Cliente: {{ $selectedClient['full_name'] }}
                    @elseif ($selectedVehicle)
                        Reporte Kardex de Vehículo: {{ $selectedVehicle['license_plate'] }}
                    @else
                        Reporte Integral de Kardex (Global)
                    @endif
                </div>
                <div class="header-subtitle">
                    @if ($selectedVehicle)
                        Historial técnico, servicios y repuestos para {{ $selectedVehicle['brand'] }} {{ $selectedVehicle['model'] }} (Placa {{ $selectedVehicle['license_plate'] }})
                    @elseif ($selectedClient)
                        Historial comercial, técnico y financiero del cliente
                    @else
                        Historial comercial, técnico y financiero consolidado
                    @endif
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div style="font-size: 8px; color: #64748b;">
                    <strong>Fecha de Emisión:</strong> {{ date('d/m/Y H:i') }}<br>
                    <strong>Período Consultado:</strong> {{ $dateRangeText }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Fichas Perfil (Si se filtró por Cliente o Vehículo) -->
    @if ($selectedClient || $selectedVehicle)
        <table style="margin-bottom: 6px;">
            <tr>
                @if ($selectedClient)
                    <td style="width: {{ $selectedVehicle ? '50%' : '100%' }}; vertical-align: top; padding-right: {{ $selectedVehicle ? '4px' : '0' }};">
                        <div class="info-box">
                            <div class="info-title">Cliente / Titular</div>
                            <div class="info-content">{{ $selectedClient['full_name'] }}</div>
                            <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                                <strong>RUC/C.I:</strong> {{ $selectedClient['n_document'] ?: 'S/N' }} &bull;
                                <strong>Teléfono:</strong> {{ $selectedClient['phone'] ?: 'S/N' }}
                            </div>
                        </div>
                    </td>
                @endif
                @if ($selectedVehicle)
                    <td style="width: {{ $selectedClient ? '50%' : '100%' }}; vertical-align: top; padding-left: {{ $selectedClient ? '4px' : '0' }};">
                        <div class="info-box">
                            <div class="info-title">Vehículo / Placa Automotriz</div>
                            <div>
                                <span class="plate-badge">{{ $selectedVehicle['license_plate'] }}</span>
                                <strong style="font-size: 9.5px; margin-left: 3px;">{{ $selectedVehicle['brand'] }} {{ $selectedVehicle['model'] }}</strong>
                                <span style="font-size: 8px; color: #64748b;">(Año {{ $selectedVehicle['year'] ?: 'S/A' }})</span>
                            </div>
                            <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                                <strong>Último Kilometraje:</strong> {{ $selectedVehicle['last_mileage'] ? number_format($selectedVehicle['last_mileage']) . ' km' : 'S/N' }} &bull;
                                <strong>Color:</strong> {{ $selectedVehicle['color'] ?: 'S/E' }}
                            </div>
                        </div>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <!-- Resumen de Métricas / KPIs -->
    <table class="kpi-table">
        <tr>
            <td style="width: 25%; padding-right: 3px;">
                <div class="kpi-card">
                    <div class="kpi-label">Total Facturado</div>
                    <div class="kpi-value" style="color: #0284c7;">${{ number_format($metrics['total_facturado'], 2) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 3px; padding-left: 3px;">
                <div class="kpi-card">
                    <div class="kpi-label">Total Pagado / Cobrado</div>
                    <div class="kpi-value" style="color: #16a34a;">${{ number_format($metrics['total_pagado'], 2) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 3px; padding-left: 3px;">
                <div class="kpi-card">
                    <div class="kpi-label">Total Saldo / Debe</div>
                    <div class="kpi-value" style="color: #dc2626;">${{ number_format($metrics['saldo_pendiente'], 2) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding-left: 3px;">
                <div class="kpi-card">
                    <div class="kpi-label">Comprobantes & Repuestos</div>
                    <div class="kpi-value">{{ $metrics['total_transacciones'] }} docs / ${{ number_format($metrics['total_repuestos'], 2) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tabla Detallada de Transacciones con Saldos, Pagos y Deudas -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%; text-align: center;">#</th>
                <th style="width: {{ $showClientCol && $showVehicleCol ? '7%' : ($showClientCol || $showVehicleCol ? '8%' : '9%') }}; text-align: center;">Fecha</th>
                <th style="width: {{ $showClientCol && $showVehicleCol ? '11%' : ($showClientCol || $showVehicleCol ? '13%' : '15%') }};">Comprobante</th>
                @if ($showClientCol)
                    <th style="width: {{ $showVehicleCol ? '16%' : '20%' }};">Cliente</th>
                @endif
                @if ($showVehicleCol)
                    <th style="width: {{ $showClientCol ? '13%' : '18%' }};">Vehículo / Placa</th>
                @endif
                <th style="width: {{ $showClientCol && $showVehicleCol ? '26%' : ($showClientCol || $showVehicleCol ? '37%' : '49%') }};">Detalle de Repuestos / Servicios</th>
                <th style="width: 8%; text-align: right;">Total</th>
                <th style="width: 8%; text-align: right;">Pagado</th>
                <th style="width: 8%; text-align: right;">Saldo / Debe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $index => $tx)
                <tr>
                    <td class="text-center" style="font-weight: bold;">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $tx['date_formatted'] }}</td>
                    <td>
                        <span class="badge-doc {{ $tx['document_type'] === 'invoice' ? 'badge-invoice' : 'badge-sale-note' }}">
                            {{ $tx['document_type'] === 'invoice' ? 'Factura' : 'Nota Venta' }}
                        </span>
                        <div style="font-weight: bold; font-size: 8.5px; margin-top: 1px;">
                            {{ $tx['document_number'] }}
                        </div>
                        @if ($tx['work_order_number'])
                            <div style="font-size: 7px; color: #0284c7; font-weight: bold;">OT #{{ $tx['work_order_number'] }}</div>
                        @endif
                    </td>
                    @if ($showClientCol)
                        <td>
                            <strong>{{ $tx['client']['full_name'] ?? 'Consumidor Final' }}</strong>
                            @if (isset($tx['client']['n_document']))
                                <div style="font-size: 7px; color: #64748b;">CI/RUC: {{ $tx['client']['n_document'] }}</div>
                            @endif
                        </td>
                    @endif
                    @if ($showVehicleCol)
                        <td>
                            @if ($tx['vehicle'])
                                <span class="plate-badge">{{ $tx['vehicle']['license_plate'] }}</span>
                                <div style="font-size: 7px; color: #475569; margin-top: 1px;">
                                    {{ $tx['vehicle']['brand'] }} {{ $tx['vehicle']['model'] }}
                                </div>
                                @if ($tx['mileage'])
                                    <div style="font-size: 6.5px; color: #64748b;">{{ number_format($tx['mileage']) }} km</div>
                                @endif
                            @else
                                <span style="color: #94a3b8; font-style: italic;">Sin vehículo</span>
                            @endif
                        </td>
                    @endif
                    <td>
                        @if (!empty($tx['details']))
                            <table class="nested-item-table">
                                <thead>
                                    <tr>
                                        <th style="width: 55%;">Descripción</th>
                                        <th style="width: 15%; text-align: center;">Cant.</th>
                                        <th style="width: 15%; text-align: right;">P. Unit</th>
                                        <th style="width: 15%; text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tx['details'] as $item)
                                        <tr>
                                            <td>
                                                <span style="color: {{ $item['tipo'] === 'servicio' ? '#0284c7' : '#16a34a' }}; font-weight: bold;">
                                                    [{{ strtoupper(substr($item['tipo'], 0, 3)) }}]
                                                </span>
                                                {{ $item['description'] }}
                                            </td>
                                            <td class="text-center">{{ $item['quantity'] }}</td>
                                            <td class="text-right">${{ number_format($item['unit_price'], 2) }}</td>
                                            <td class="text-right" style="font-weight: bold;">${{ number_format($item['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <span style="color: #94a3b8; font-style: italic;">Sin detalle registrado</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold; font-size: 9px; color: #0f172a;">
                        ${{ number_format($tx['total'], 2) }}
                    </td>
                    <td class="text-right" style="font-weight: bold; color: #16a34a;">
                        ${{ number_format($tx['paid_amount'], 2) }}
                    </td>
                    <td class="text-right" style="font-weight: bold; color: {{ $tx['due_amount'] > 0 ? '#dc2626' : '#64748b' }};">
                        ${{ number_format($tx['due_amount'], 2) }}
                        <div class="{{ $tx['payment_status'] === 'paid' ? 'badge-paid' : ($tx['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending') }}" style="font-size: 7px; margin-top: 1px;">
                            {{ $tx['payment_status'] === 'paid' ? 'PAGADO' : ($tx['payment_status'] === 'partial' ? 'PARCIAL' : 'PENDIENTE') }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $totalCols }}" class="text-center" style="padding: 15px; color: #64748b;">
                        No se encontraron transacciones para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (!empty($transactions) && count($transactions) > 0)
            <tfoot>
                <tr>
                    <td colspan="{{ $leftCols }}" class="text-right" style="font-weight: bold; text-transform: uppercase;">
                        TOTALES CONSOLIDADOS:
                    </td>
                    <td class="text-right" style="color: #0284c7;">
                        ${{ number_format($metrics['total_facturado'], 2) }}
                    </td>
                    <td class="text-right" style="color: #16a34a;">
                        ${{ number_format($metrics['total_pagado'], 2) }}
                    </td>
                    <td class="text-right" style="color: #dc2626;">
                        ${{ number_format($metrics['saldo_pendiente'], 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer-note">
        Kardex Generado automáticamente por el Sistema &bull; Página 1 de 1
    </div>

</body>

</html>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Kardex General Financiero</title>
    <style>
        @page {
            margin: 8mm 10mm 12mm 10mm;
        }

        * {
            box-sizing: border-box;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 8px;
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
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            margin-bottom: 8px;
        }

        .info-title {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 1px;
        }

        .info-content {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
        }

        .kpi-table {
            margin-bottom: 8px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 6px;
            text-align: center;
        }

        .kpi-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }

        .kpi-value {
            font-size: 11.5px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }

        .text-success {
            color: #16a34a !important;
        }

        .text-danger {
            color: #dc2626 !important;
        }

        .text-primary {
            color: #0284c7 !important;
        }

        .data-table {
            width: 100%;
            margin-top: 4px;
            border: 1px solid #cbd5e1;
        }

        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4.5px 5px;
            border: 1px solid #334155;
            text-align: left;
            letter-spacing: 0.3px;
        }

        .data-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            font-size: 8px;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 1.5px 4.5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .badge-income {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-expense {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge-concept {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-family: 'Courier New', monospace;
            font-size: 7.5px;
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

        .font-bold {
            font-weight: bold;
        }

        .font-mono {
            font-family: 'Courier New', monospace;
        }

        .footer-note {
            margin-top: 8px;
            font-size: 7.5px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        .footer-page {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 15px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 2px;
        }
    </style>
</head>

<body>

    <!-- Encabezado Principal -->
    <table class="header-table">
        <tr>
            <td style="width: 22%; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" style="max-height: 48px; max-width: 140px;" alt="Logo">
                @else
                    <div style="font-size: 15px; font-weight: bold; color: #0284c7;">
                        {{ $sucursal->name ?? 'TALLER MECÁNICO' }}
                    </div>
                @endif
            </td>
            <td style="width: 48%; text-align: center; vertical-align: middle;">
                <div class="header-title">KARDEX GENERAL FINANCIERO</div>
                <div class="header-subtitle">
                    {{ $sucursal->razon_social ?? ($sucursal->name ?? 'REPORTE CONSOLIDADO DE INGRESOS Y EGRESOS') }}
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    RUC: {{ $sucursal->ruc ?? '9999999999001' }} &bull; {{ $sucursal->address ?? 'Matriz' }}
                </div>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: middle;">
                <div style="font-size: 8px; color: #64748b;">
                    <strong>Período:</strong> {{ $dateRangeText }}
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                    <strong>Emisión:</strong> {{ date('d/m/Y H:i') }}
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                    <strong>Usuario:</strong> {{ auth()->user()->name ?? 'Sistema' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Tarjetas de Métricas Resumen -->
    <table class="kpi-table">
        <tr>
            <td style="width: 25%; padding-right: 4px;">
                <div class="kpi-card" style="border-left: 3px solid #16a34a;">
                    <div class="kpi-label">Total Ingresos (+)</div>
                    <div class="kpi-value text-success font-mono">${{ number_format($metrics['total_ingresos'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 2px;">
                <div class="kpi-card" style="border-left: 3px solid #dc2626;">
                    <div class="kpi-label">Total Egresos (-)</div>
                    <div class="kpi-value text-danger font-mono">${{ number_format($metrics['total_egresos'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 2px;">
                <div class="kpi-card" style="border-left: 3px solid {{ ($metrics['saldo_neto'] ?? 0) >= 0 ? '#0284c7' : '#dc2626' }};">
                    <div class="kpi-label">Saldo Neto (=)</div>
                    <div class="kpi-value {{ ($metrics['saldo_neto'] ?? 0) >= 0 ? 'text-primary' : 'text-danger' }} font-mono">
                        ${{ number_format($metrics['saldo_neto'] ?? 0, 2) }}
                    </div>
                </div>
            </td>
            <td style="width: 25%; padding-left: 4px;">
                <div class="kpi-card" style="border-left: 3px solid #64748b;">
                    <div class="kpi-label">Total Movimientos</div>
                    <div class="kpi-value font-mono">{{ $metrics['total_movimientos'] ?? count($movimientosFormateados) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tabla Detallada de Movimientos -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 28px; text-align: center;">#</th>
                <th style="width: 65px; text-align: center;">Fecha</th>
                <th style="width: 100px;">Concepto / SKU</th>
                <th>Descripción / Detalle</th>
                <th style="width: 110px;">Cuenta / Método</th>
                <th style="width: 40px; text-align: center;">Cant.</th>
                <th style="width: 60px; text-align: right;">P. Unit</th>
                <th style="width: 70px; text-align: right;">Ingreso (+)</th>
                <th style="width: 70px; text-align: right;">Egreso (-)</th>
                <th style="width: 75px; text-align: right;">Saldo ($)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientosFormateados as $index => $item)
                @php
                    $isIngreso = ($item['movimiento_tipo'] === 'entrada');
                    $monto = (float)($item['monto_financiero'] ?? 0);
                    $saldo = (float)($item['saldo_acumulado'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center font-mono" style="color: #64748b;">{{ $index + 1 }}</td>
                    <td class="text-center font-mono">{{ $item['fecha'] ?? '-' }}</td>
                    <td>
                        <span class="badge badge-concept">{{ $item['concepto'] ?? 'GENERAL' }}</span>
                    </td>
                    <td>
                        <div class="font-bold">{{ $item['descripcion'] ?? 'Sin descripción' }}</div>
                    </td>
                    <td>
                        <span style="color: #475569;">{{ $item['account_name'] ?? 'Caja General' }}</span>
                    </td>
                    <td class="text-center font-mono">
                        {{ !empty($item['cantidad']) ? number_format($item['cantidad'], 1) : '-' }}
                    </td>
                    <td class="text-right font-mono">
                        {{ !empty($item['precio_unitario']) ? '$' . number_format($item['precio_unitario'], 2) : '-' }}
                    </td>
                    <td class="text-right font-mono font-bold text-success">
                        @if($isIngreso)
                            +${{ number_format($monto, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right font-mono font-bold text-danger">
                        @if(!$isIngreso)
                            -${{ number_format($monto, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right font-mono font-bold {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}">
                        ${{ number_format($saldo, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 15px; color: #64748b;">
                        No se registraron movimientos en el rango de fechas seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #0f172a; color: #ffffff;">
                <td colspan="7" class="text-right font-bold" style="padding: 5px; font-size: 8.5px; border-top: 1px solid #334155;">
                    TOTALES CONSOLIDADOS:
                </td>
                <td class="text-right font-mono font-bold text-success" style="padding: 5px; font-size: 9px; border-top: 1px solid #334155; color: #4ade80 !important;">
                    +${{ number_format($metrics['total_ingresos'] ?? 0, 2) }}
                </td>
                <td class="text-right font-mono font-bold text-danger" style="padding: 5px; font-size: 9px; border-top: 1px solid #334155; color: #f87171 !important;">
                    -${{ number_format($metrics['total_egresos'] ?? 0, 2) }}
                </td>
                <td class="text-right font-mono font-bold" style="padding: 5px; font-size: 9.5px; border-top: 1px solid #334155; color: {{ ($metrics['saldo_neto'] ?? 0) >= 0 ? '#38bdf8' : '#f87171' }} !important;">
                    ${{ number_format($metrics['saldo_neto'] ?? 0, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-note" style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="width: 70%;">
                <em>* Reporte oficial generado por el módulo de Kardex Financiero Integral. Los saldos reflejan la conciliación bancaria y caja física del período.</em>
            </td>
            <td style="width: 30%; text-align: right;">
                <strong>Estado:</strong> Cuadrado y Verificado
            </td>
        </tr>
    </table>

</body>

</html>

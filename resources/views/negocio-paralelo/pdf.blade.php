<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Movimientos - FRITADAS LUXURY</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 10px;
            background-color: white;
            color: #333;
            line-height: 1.4;
        }

        .container {
            width: 100%;
            margin: 0 auto;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header-logo {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-title {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            color: #555;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 10px;
            color: #888;
            text-align: right;
            margin-top: 5px;
        }

        .period-box {
            background-color: #f1f8e9;
            border-left: 4px solid #2e7d32;
            padding: 8px 12px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }

        .summary-card {
            background-color: #fafafa;
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: center;
            border-radius: 4px;
        }

        .summary-title {
            font-size: 9px;
            color: #777;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
        }

        .value-income {
            color: #2e7d32;
        }

        .value-expense {
            color: #c62828;
        }

        .value-balance {
            color: #1565c0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .data-table th {
            background-color: #f5f5f5;
            color: #333;
            text-transform: uppercase;
            font-size: 9px;
            font-weight: bold;
            padding: 8px;
            border-bottom: 2px solid #ccc;
            text-align: left;
        }

        .data-table td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 10px;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #fbfbfb;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-uppercase {
            text-transform: uppercase;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-income {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge-expense {
            background-color: #ffebee;
            color: #c62828;
        }

        .badge-account {
            border: 1px solid #e0e0e0;
            color: #666;
            background-color: #fff;
        }

        .total-row {
            background-color: #f5f5f5 !important;
            font-weight: bold;
            border-top: 2px solid #999;
        }

        .total-row td {
            font-size: 11px;
            padding: 10px 8px;
            border-bottom: 2px solid #999;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    @php
    $sucursal = \App\Models\Config\Sucursale::find(auth()->user()->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();
    $logoBase64 = '';

    $logoPath = null;
    if ($sucursal && $sucursal->logo) {
    $logoPath = public_path($sucursal->logo);
    if (!file_exists($logoPath)) {
    $cleanLogo = str_replace('storage/', '', $sucursal->logo);
    $logoPath = storage_path('app/public/' . $cleanLogo);
    }
    }

    if (!$logoPath || !file_exists($logoPath)) {
    $logoPath = public_path('assets/img/brand/logo.jpeg');
    }

    if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $logoMime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : (($ext === 'svg') ? 'image/svg+xml' : 'image/jpeg'));
    $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
    }
    @endphp
    <div class="container">
        <!-- Header -->
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="header-logo" style="width: 50%;">
                    @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo" style="height: 60px; border: none;">
                    @else
                    FRITADAS LUXURY
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="header-title">Movimientos de venta Fritada</div>
                    <div class="header-subtitle">Generado el {{ date('d/m/Y H:i:s') }}</div>
                </td>
            </tr>
        </table>

        <!-- Period Label -->
        <div class="period-box">
            {{ $rangeLabel }}
        </div>

        <!-- Summary Statistics -->
        <table class="summary-table">
            <tr>
                <td style="width: 32%; padding-right: 10px;">
                    <div class="summary-card" style="border-top: 4px solid #2e7d32;">
                        <div class="summary-title">Total Ingresos</div>
                        <div class="summary-value value-income">+${{ number_format($totalIncomes, 2) }}</div>
                    </div>
                </td>
                <td style="width: 32%; padding-right: 10px;">
                    <div class="summary-card" style="border-top: 4px solid #c62828;">
                        <div class="summary-title">Total Egresos</div>
                        <div class="summary-value value-expense">-${{ number_format($totalExpenses, 2) }}</div>
                    </div>
                </td>
                <td style="width: 32%;">
                    <div class="summary-card" style="border-top: 4px solid #1565c0;">
                        <div class="summary-title">Balance Neto</div>
                        <div class="summary-value value-balance" style="color: {{ $balance >= 0 ? '#2e7d32' : '#c62828' }}">
                            {{ $balance >= 0 ? '+' : '' }}${{ number_format($balance, 2) }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Transactions Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Fecha</th>
                    <th style="width: 60px;">Tipo</th>
                    <th>Concepto / Descripción</th>
                    <th style="width: 110px;">Detalle</th>
                    <th style="width: 90px;" class="text-center">Cuenta</th>
                    <th style="width: 85px;" class="text-right">Ingresos (+)</th>
                    <th style="width: 85px;" class="text-right">Egresos (-)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $item)
                <tr>
                    <td>{{ $item->date->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ $item->type === 'income' ? 'badge-income' : 'badge-expense' }}">
                            {{ $item->type === 'income' ? 'Ingreso' : 'Egreso' }}
                        </span>
                    </td>
                    <td class="text-uppercase" style="font-weight: 500;">{{ $item->description }}</td>
                    <td class="text-uppercase">
                        @if($item->type === 'income')
                        {{ $item->quantity }} X ${{ number_format($item->unit_cost, 2) }}
                        @else
                        {{ $item->unit ?: 'Sin unidad' }}
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge badge-account">{{ $item->account }}</span>
                    </td>
                    <!-- Ingresos -->
                    <td class="text-right value-income" style="font-weight: bold;">
                        @if($item->type === 'income')
                        +${{ number_format($item->amount, 2) }}
                        @else
                        -
                        @endif
                    </td>
                    <!-- Egresos -->
                    <td class="text-right value-expense" style="font-weight: bold;">
                        @if($item->type === 'expense')
                        -${{ number_format($item->amount, 2) }}
                        @else
                        -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #888;">
                        No hay movimientos registrados para este período.
                    </td>
                </tr>
                @endforelse

                <!-- Totales Summary Row -->
                @if(count($transactions) > 0)
                <tr class="total-row">
                    <td colspan="5" class="text-right text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">Resumen de Totales:</td>
                    <td class="text-right value-income">+${{ number_format($totalIncomes, 2) }}</td>
                    <td class="text-right value-expense">-${{ number_format($totalExpenses, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Page Footer -->
        <div class="footer">
            Reporte generado automáticamente por el Módulo de Negocio Paralelo.
        </div>
    </div>
</body>

</html>
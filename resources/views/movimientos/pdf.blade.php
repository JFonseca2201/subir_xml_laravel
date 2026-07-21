<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero Consolidado</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 15px;
            background-color: white;
            color: #1e293b;
            line-height: 1.4;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #cbd5e1;
        }

        .header h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
            color: #0f172a;
            font-weight: 800;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 0;
        }

        .header .info {
            margin-top: 8px;
            font-size: 10px;
            color: #64748b;
        }

        .kpi-grid {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 8px;
        }

        .kpi-box {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .kpi-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .kpi-val {
            font-size: 14px;
            font-weight: 800;
        }

        .section-banner {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            margin-top: 20px;
            margin-bottom: 8px;
            text-transform: uppercase;
            page-break-after: avoid;
        }

        .section-banner-income {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .section-banner-transfer {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #2563eb;
        }

        .section-banner-expense {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.data-table th {
            padding: 8px 6px;
            text-align: left;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            color: #475569;
            background-color: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
        }

        table.data-table td {
            padding: 7px 6px;
            text-align: left;
            color: #334155;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        table.data-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: 700;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }

        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    @php
        $incomes = collect($movements)->filter(function($m) {
            return $m->type === 'income' || $m->type === 0;
        });

        $transfers = collect($movements)->filter(function($m) {
            return $m->type === 'transfer';
        });

        $expenses = collect($movements)->filter(function($m) {
            return $m->type === 'expense' || $m->type === 1;
        });

        $totalIncome = $incomes->sum('amount');
        $totalTransfer = $transfers->sum('amount');
        $totalExpense = $expenses->sum('amount');
        $balance = $totalIncome - $totalExpense;
    @endphp

    <div class="container">
        <div class="header">
            <h1>Reporte Financiero Consolidado</h1>
            <p class="subtitle">Sistema de Gestión Comercial - Módulo Financiero</p>
            <div class="info">
                <span>Fecha de Emisión: {{ date('d/m/Y H:i:s') }}</span> | <span>Total Movimientos: {{ count($movements) }}</span>
            </div>
        </div>

        <!-- Resumen KPI -->
        <table class="kpi-grid">
            <tr>
                <td style="width: 25%; padding: 0;">
                    <div class="kpi-box" style="background-color: #f0fdf4; border-color: #bbf7d0;">
                        <div class="kpi-title" style="color: #166534;">Ingresos Totales</div>
                        <div class="kpi-val" style="color: #15803d;">${{ number_format($totalIncome, 2) }}</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0;">
                    <div class="kpi-box" style="background-color: #eff6ff; border-color: #bfdbfe;">
                        <div class="kpi-title" style="color: #1e40af;">Transferencias</div>
                        <div class="kpi-val" style="color: #1d4ed8;">${{ number_format($totalTransfer, 2) }}</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0;">
                    <div class="kpi-box" style="background-color: #fef2f2; border-color: #fecaca;">
                        <div class="kpi-title" style="color: #991b1b;">Egresos Totales</div>
                        <div class="kpi-val" style="color: #dc2626;">${{ number_format($totalExpense, 2) }}</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0;">
                    <div class="kpi-box" style="background-color: #f8fafc; border-color: #cbd5e1;">
                        <div class="kpi-title" style="color: #334155;">Balance Neto</div>
                        <div class="kpi-val" style="color: #0f172a;">${{ number_format($balance, 2) }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- SECCIÓN 1: INGRESOS -->
        <div class="section-banner section-banner-income">
            1. REGISTROS DE INGRESOS ({{ count($incomes) }}) — SUBTOTAL: ${{ number_format($totalIncome, 2) }}
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Fecha</th>
                    <th>Descripción</th>
                    <th style="width: 200px;">Cuenta</th>
                    <th style="width: 100px;" class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incomes as $m)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($m->entry_date)->format('d/m/Y') }}</td>
                    <td>{{ $m->description ?? ($m->movable?->descripcion ?? 'Ingreso General') }}</td>
                    <td>{{ $m->account ? $m->account->name : 'N/A' }}</td>
                    <td class="text-right font-bold" style="color: #15803d;">+${{ number_format($m->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 12px;">No se encontraron registros de ingresos.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- SECCIÓN 2: TRANSFERENCIAS -->
        <div class="section-banner section-banner-transfer">
            2. TRANSFERENCIAS ENTRE CUENTAS ({{ count($transfers) }}) — SUBTOTAL: ${{ number_format($totalTransfer, 2) }}
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Fecha</th>
                    <th>Descripción</th>
                    <th style="width: 250px;">Cuentas (Origen → Destino)</th>
                    <th style="width: 100px;" class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfers as $m)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($m->entry_date)->format('d/m/Y') }}</td>
                    <td>{{ $m->description ?? 'Transferencia entre cuentas' }}</td>
                    <td>
                        @if (isset($m->metadata['from_account_name']) && isset($m->metadata['to_account_name']))
                            <span style="color: #dc2626; font-weight: bold;">{{ $m->metadata['from_account_name'] }}</span> → 
                            <span style="color: #15803d; font-weight: bold;">{{ $m->metadata['to_account_name'] }}</span>
                        @elseif(isset($m->metadata['from_account_name']))
                            <span style="color: #dc2626; font-weight: bold;">{{ $m->metadata['from_account_name'] }}</span> → Externo
                        @elseif(isset($m->metadata['to_account_name']))
                            Externo → <span style="color: #15803d; font-weight: bold;">{{ $m->metadata['to_account_name'] }}</span>
                        @else
                            {{ $m->metadata['from_account'] ?? 'N/A' }} → {{ $m->metadata['to_account'] ?? 'N/A' }}
                        @endif
                    </td>
                    <td class="text-right font-bold" style="color: #1d4ed8;">${{ number_format($m->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 12px;">No se encontraron transferencias registradas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- SECCIÓN 3: EGRESOS -->
        <div class="section-banner section-banner-expense">
            3. REGISTROS DE EGRESOS ({{ count($expenses) }}) — SUBTOTAL: ${{ number_format($totalExpense, 2) }}
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Fecha</th>
                    <th>Descripción</th>
                    <th style="width: 200px;">Cuenta</th>
                    <th style="width: 100px;" class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $m)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($m->entry_date)->format('d/m/Y') }}</td>
                    <td>{{ $m->description ?? ($m->movable?->descripcion ?? 'Egreso General') }}</td>
                    <td>{{ $m->account ? $m->account->name : 'N/A' }}</td>
                    <td class="text-right font-bold" style="color: #dc2626;">-${{ number_format($m->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 12px;">No se encontraron registros de egresos.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            <p>Sistema de Gestión © {{ date('Y') }} | Reporte generado automáticamente por tipo de movimiento</p>
        </div>
    </div>
</body>

</html>
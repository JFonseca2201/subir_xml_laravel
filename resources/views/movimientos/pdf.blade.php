<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            background-color: white;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ccc;
        }

        .header h1 {
            font-size: 20px;
            margin: 0 0 5px 0;
            color: #333;
            font-weight: bold;
        }

        .header .subtitle {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .header .info {
            margin-top: 10px;
            font-size: 11px;
            color: #888;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead {
            border-bottom: 2px solid #333;
        }

        th {
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            color: #333;
            border-bottom: 1px solid #ccc;
        }

        td {
            padding: 12px 8px;
            text-align: left;
            color: #333;
            border-bottom: 1px solid #eee;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: normal;
            text-transform: uppercase;
        }

        .badge-income {
            color: #2e7d32;
        }

        .badge-expense {
            color: #c62828;
        }

        .badge-transfer {
            color: #f9a825;
        }

        .total-section {
            margin-top: 30px;
            padding: 15px;
            text-align: right;
            border-top: 2px solid #333;
        }

        .total-section .label {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            color: #666;
        }

        .total-section .amount {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        .summary-section {
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .summary-row:last-child {
            margin-bottom: 0;
        }

        .summary-label {
            font-size: 11px;
            color: #666;
            font-weight: normal;
        }

        .summary-value {
            font-size: 11px;
            color: #333;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .amount {
            font-weight: bold;
            color: #333;
        }

        .amount-income {
            font-weight: bold;
            color: #2e7d32;
        }

        .amount-expense {
            font-weight: bold;
            color: #c62828;
        }

        .amount-transfer {
            font-weight: bold;
            color: #f9a825;
        }

        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Reporte Financiero</h1>
            <p class="subtitle">Sistema de Gestión Comercial</p>
            <div class="info">
                <span>Fecha: {{ date('d/m/Y H:i:s') }}</span> | <span>Registros: {{ $summary['totalCount'] }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Cuenta</th>
                    <th style="width: 75px;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($movement->entry_date)->format('d/m/Y') }}</td>
                        <td>
                            @if ($movement->type == 'income')
                                <span class="badge badge-income">Ingreso</span>
                            @elseif($movement->type == 'expense')
                                <span class="badge badge-expense">Egreso</span>
                            @else
                                <span class="badge badge-transfer">{{ $movement->type }}</span>
                            @endif
                        </td>
                        <td>{{ $movement->description ?? ($movement->movable?->descripcion ?? 'N/A') }}</td>
                        <td>
                            @if ($movement->type == 'transfer')
                                @if (isset($movement->metadata['from_account_name']) && isset($movement->metadata['to_account_name']))
                                    {{ $movement->metadata['from_account_name'] }} ->
                                    {{ $movement->metadata['to_account_name'] }}
                                @elseif(isset($movement->metadata['from_account_name']))
                                    {{ $movement->metadata['from_account_name'] }} -> Externo
                                @elseif(isset($movement->metadata['to_account_name']))
                                    Externo -> {{ $movement->metadata['to_account_name'] }}
                                @else
                                    {{ $movement->metadata['from_account'] ?? 'N/A' }} →
                                    {{ $movement->metadata['to_account'] ?? 'N/A' }}
                                @endif
                            @else
                                {{ $movement->account ? $movement->account->name : 'N/A' }}
                            @endif
                        </td>
                        <td
                            class="{{ $movement->type == 'income' ? 'amount-income' : ($movement->type == 'expense' ? 'amount-expense' : 'amount-transfer') }}">
                            {{ $movement->type == 'income' ? '+' : ($movement->type == 'expense' ? '-' : '') }}
                            ${{ number_format($movement->amount, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-section">
            <div class="summary-row">
                <span class="summary-label">Total Ingresos:</span>
                <span class="summary-value">${{ number_format($summary['totalIncome'], 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Egresos:</span>
                <span class="summary-value">${{ number_format($summary['totalExpense'], 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Balance:</span>
                <span class="summary-value">${{ number_format($summary['balance'], 2) }}</span>
            </div>
        </div>

        <div class="total-section">
            <div class="label">Balance General</div>
            <p class="amount">${{ number_format($summary['balance'], 2) }}</p>
        </div>

        <div class="footer">
            <p>Sistema de Gestión © {{ date('Y') }} | Generado automáticamente</p>
        </div>
    </div>
</body>

</html>

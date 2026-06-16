<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de {{ ucfirst($type_string == 'income' ? 'Ingreso' : ($type_string == 'expense' ? 'Egreso' : 'Transferencia')) }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 40px;
            background-color: white;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .header .logo-container {
            margin-bottom: 15px;
        }

        .header .logo-container img {
            max-height: 80px;
            max-width: 250px;
        }

        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: #333;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #666;
            padding: 10px 0;
            width: 150px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-value {
            display: table-cell;
            color: #333;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .amount-section {
            text-align: center;
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 40px;
            border: 1px solid #eee;
        }

        .amount-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }

        .amount-value {
            font-size: 36px;
            font-weight: bold;
            margin: 0;
        }

        .amount-income {
            color: #2e7d32;
        }

        .amount-expense {
            color: #c62828;
        }

        .amount-transfer {
            color: #f9a825;
        }



        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #999;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        @media print {
            body {
                padding: 0;
            }

            .container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            @if(isset($logoBase64) && $logoBase64)
            <div class="logo-container">
                <img src="{{ $logoBase64 }}" alt="Logo Empresa">
            </div>
            @endif
            @if(isset($custom_title))
                <h1>{{ $custom_title }}</h1>
            @else
                <h1>Comprobante de {{ $type_string == 'income' ? 'Ingreso' : ($type_string == 'expense' ? 'Egreso' : 'Transferencia') }}</h1>
            @endif
            <p class="subtitle">Sistema de Gestión Comercial</p>
            <p style="margin-top: 10px; font-size: 12px; color: #888;">Comprobante #{{ str_pad($movement->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>

        <div class="amount-section">
            <span class="amount-label">Monto Total</span>
            <p class="amount-value {{ $type_string == 'income' ? 'amount-income' : ($type_string == 'expense' ? 'amount-expense' : 'amount-transfer') }}">
                ${{ number_format($movement->amount, 2) }}
            </p>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Fecha:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($movement->entry_date)->format('Y-m-d') }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Concepto:</div>
                <div class="info-value" style="text-transform: uppercase;">{{ $movement->description ?? ($movement->movable?->descripcion ?? 'N/A') }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">
                    {{ $type_string == 'transfer' ? 'Cuentas:' : 'Cuenta:' }}
                </div>
                <div class="info-value">
                    {{ $account_name }}
                </div>
            </div>

            @if($movement->work_order_number || $movement->invoice_number)
            <div class="info-row">
                <div class="info-label">Referencia:</div>
                <div class="info-value">
                    {{ $movement->work_order_number ? 'O/T ' . $movement->work_order_number : '' }}
                    {{ $movement->work_order_number && $movement->invoice_number ? ' / ' : '' }}
                    {{ $movement->invoice_number ? $movement->invoice_number : '' }}
                </div>
            </div>
            @endif

            <div class="info-row">
                <div class="info-label">Registrado el:</div>
                <div class="info-value">{{ $movement->created_at->format('Y-m-d') }}</div>
            </div>
        </div>



        <div class="footer">
            <p>Sistema de Gestión © {{ date('Y') }} | Documento generado automáticamente</p>
        </div>
    </div>
</body>

</html>
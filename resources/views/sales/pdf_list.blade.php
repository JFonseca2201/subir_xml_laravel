<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: #667eea;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }

        .header .info {
            margin-top: 15px;
            font-size: 12px;
            color: #888;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }

        thead {
            background-color: #667eea;
        }

        th {
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            color: white;
            border: 1px solid #5568d3;
        }

        td {
            padding: 10px 8px;
            text-align: left;
            color: #333;
            border: 1px solid #ddd;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-quote {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-sale-note {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-invoice {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }

        .badge-completed {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .badge-pending {
            background-color: #ffebee;
            color: #c62828;
        }

        .badge-canceled {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .total-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #667eea;
            color: white;
            text-align: right;
        }

        .total-section .label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .total-section .amount {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .summary-cards {
            display: table;
            width: 100%;
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 10px;
        }

        .summary-card {
            display: table-cell;
            padding: 15px;
            background-color: #667eea;
            color: white;
            text-align: center;
            width: 25%;
        }

        .summary-card .label {
            font-size: 10px;
            margin-bottom: 5px;
        }

        .summary-card .value {
            font-size: 18px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .amount {
            font-weight: bold;
            color: #667eea;
        }
        .amount-before::before {
            font-weight: bold;
            color: #667eea;
font-size: x-large;            
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reporte de Ventas</h1>
            <p class="subtitle">Sistema de Gestión Comercial</p>
            <div class="info">
                <span>Fecha: {{ date('d/m/Y H:i:s') }}</span> | <span>Registros: {{ $sales->count() }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>N° Documento</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Estado</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sales as $sale)
                <tr>
                    <td><strong>{{ $sale->document_number }}</strong></td>
                    <td>
                        @if($sale->document_type == 'quote')
                            <span class="badge badge-quote">Cotización</span>
                        @elseif($sale->document_type == 'sale_note')
                            <span class="badge badge-sale-note">Nota Venta</span>
                        @elseif($sale->document_type == 'invoice')
                            <span class="badge badge-invoice">Factura</span>
                        @else
                            {{ $sale->document_type }}
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($sale->service_date)->format('d/m/Y') }}</td>
                    <td>{{ $sale->client ? ($sale->client->full_name ?? $sale->client->name ?? 'N/A') : 'N/A' }}</td>
                    <td>{{ $sale->vehicle ? $sale->vehicle->license_plate : 'N/A' }}</td>
                    <td>
                        @if($sale->status == 'completed')
                            <span class="badge badge-completed">Completada</span>
                        @elseif($sale->status == 'pending')
                            <span class="badge badge-pending">Pendiente</span>
                        @elseif($sale->status == 'canceled')
                            <span class="badge badge-canceled">Anulada</span>
                        @else
                            {{ $sale->status }}
                        @endif
                    </td>
                    <td class="amount-before">${{ number_format($sale->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-cards">
            <div class="summary-card">
                <div class="label">Cotizaciones</div>
                <div class="value">{{ $sales->where('document_type', 'quote')->count() }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Notas de Venta</div>
                <div class="value">{{ $sales->where('document_type', 'sale_note')->count() }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Facturas</div>
                <div class="value">{{ $sales->where('document_type', 'invoice')->count() }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Pagadas</div>
                <div class="value">{{ $sales->where('payment_status', 'paid')->count() }}</div>
            </div>
        </div>

        <div class="total-section">
            <div class="label">Total General</div>
            <p class="amount-before" style="font-size: 20px;">${{ number_format($sales->sum('total'), 2) }}</p>
        </div>

        <div class="footer">
            <p>Sistema de gestión {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>

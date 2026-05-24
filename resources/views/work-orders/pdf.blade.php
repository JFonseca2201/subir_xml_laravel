<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Trabajo {{ $workOrder->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .order-number {
            font-size: 20px;
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
            font-size: 14px;
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
            font-size: 14px;
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
            border-bottom: 2px solid #333;
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
            font-size: 14px;
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
            border-top: 1px solid #333;
            margin-top: 80px;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Encabezado -->
    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
        <tr>
            <td style="width: 20%; vertical-align: middle; text-align: center;">
                <img style="height: 110px;background:black;"
                    src="{{ public_path('assets/img/brand/logo.jpeg') }}">
            </td>
            <td style="width: 40%; vertical-align: middle; text-align: center; font-weight: bold;">
                ORDEN DE TRABAJO
            </td>
            <td style="width: 40%; vertical-align: middle; text-align: center;">
                <div style="font-size: 16px; font-weight: bold; color: #d11f008e;">#{{ str_pad($workOrder->number, 6, '0', STR_PAD_LEFT) }}</div>
                
            </td>
        </tr>
    </table>

    <!-- Información del vehículo y cliente -->
    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse; text-transform: uppercase;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 20px;">
                <div class="info-title">DATOS DEL VEHÍCULO</div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span> {{ $workOrder->created_at ? date('d/m/Y', strtotime($workOrder->created_at)) : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Placa:</span> {{ $workOrder->vehicle ? $workOrder->vehicle->license_plate : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Modelo:</span> {{ $workOrder->vehicle ? $workOrder->vehicle->brand . ' ' . $workOrder->vehicle->model : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Kilometraje:</span> {{ $workOrder->mileage ? $workOrder->mileage . ' km' : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Tipo:</span> {{ $workOrder->vehicle ? $workOrder->vehicle->vehicle_type : 'N/A' }}
                </div>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 20px;">
                <div class="info-title">DATOS DEL CLIENTE</div>
                <div class="info-row">
                    <span class="info-label">Cédula/RUC:</span> {{ $workOrder->client ? $workOrder->client->n_document : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Nombre:</span> {{ $workOrder->client ? $workOrder->client->full_name : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Dirección:</span> {{ $workOrder->client ? $workOrder->client->address : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Teléfono:</span> {{ $workOrder->client ? $workOrder->client->phone : 'N/A' }}
                </div>
                <div class="info-row">
                    <span class="info-label">Correo:</span> {{ $workOrder->client ? $workOrder->client->email : 'N/A' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Lista de trabajos -->
    <div class="items-section">
        <div class="items-title">TRABAJOS REALIZADOS</div>
        <table>
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
                @foreach($workOrder->items as $item)
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
                <div class="observations-text">
                    {{ $workOrder->observations ?? 'Sin observaciones' }}
                </div>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 20px;">
                <div class="info-title">RESUMEN</div>
                <div class="info-row" style="display: flex; justify-content: space-between;">
                    <span class="info-label">Subtotal:</span>
                    <span>${{ number_format($grossSubtotal, 2) }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between;">
                    <span class="info-label">Descuentos:</span>
                    <span>${{ number_format($totalDiscount, 2) }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; margin-top: 10px;">
                    <span class="info-label">TOTAL:</span>
                    <span style="color: #d32f2f;">${{ number_format($total, 2) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Pie de página -->
    <table style="width: 100%; margin-top: 40px; border-top: 1px solid #333; border-collapse: collapse;">

        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 20px; padding-top: 20px;">
                <div class="signature-line">
                    <strong>{{ $workOrder->client ? $workOrder->client->full_name : 'Cliente' }}</strong>
                    <br>{{ $workOrder->client ? $workOrder->client->n_document : 'S/N' }}
                </div>
            </td>
            <td style="width: 48%; vertical-align: top; padding-left: 20px; padding-top: 20px;">
                <div class="signature-line">
                    @if($workOrder->technicians && $workOrder->technicians->count() > 0)
                    @foreach($workOrder->technicians as $technician)
                    {{ $technician->first_name }} {{ $technician->last_name }}@if(!$loop->last){{ ', ' }}@endif
                    @endforeach
                    @else
                    Técnico
                    @endif
                    <br>Técnico(s)
                </div>
            </td>
        </tr>
    </table>
</body>

</html>
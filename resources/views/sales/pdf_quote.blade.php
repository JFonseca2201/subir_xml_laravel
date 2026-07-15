<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Cotización #{{ $quote->document_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #333333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .header-table td {
            vertical-align: top;
            border: none;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }

        .company-info {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
        }

        .document-title-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }

        .document-title-card h2 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #1e293b;
            text-transform: uppercase;
        }

        .document-title-card .number {
            font-size: 18px;
            font-weight: bold;
            color: #666cff;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #666cff;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .info-block {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }

        .info-grid {
            width: 100%;
        }

        .info-grid td {
            padding: 4px 0;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            color: #64748b;
            width: 30%;
        }

        .info-value {
            color: #1e293b;
            width: 70%;
        }

        .items-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            padding: 8px 10px;
            text-align: left;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .totals-table {
            width: 40%;
            float: right;
            margin-top: 10px;
        }

        .totals-table td {
            padding: 6px 10px;
            font-size: 12px;
        }

        .totals-label {
            font-weight: bold;
            color: #64748b;
            text-align: right;
        }

        .totals-value {
            text-align: right;
            font-weight: bold;
            color: #1e293b;
        }

        .grand-total-row td {
            border-top: 2px solid #666cff;
            font-size: 14px;
            color: #666cff;
            padding-top: 10px;
        }

        .observations-block {
            margin-top: 30px;
            clear: both;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
        }

        .observations-title {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
    </style>
</head>

<body>
    @php
    $sucursal = \App\Models\Config\Sucursale::find($quote->user->sucursale_id ?? 1) ?? \App\Models\Config\Sucursale::first();

    // Convert sucursal logo to Base64 to avoid exposing absolute local path inside the PDF
    $logoBase64 = '';
    $logoPath = null;
    if ($sucursal && $sucursal->logo) {
    $tempPath = public_path($sucursal->logo);
    if (file_exists($tempPath)) {
    $logoPath = $tempPath;
    } else {
    $cleanLogo = str_replace('storage/', '', $sucursal->logo);
    $tempPath = storage_path('app/public/' . $cleanLogo);
    if (file_exists($tempPath)) {
    $logoPath = $tempPath;
    }
    }
    }

    if (!$logoPath || !file_exists($logoPath)) {
    $logoPath = public_path('assets/img/brand/logo.jpeg');
    }

    if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoMime = 'image/jpeg';
    $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    if ($ext === 'png') {
    $logoMime = 'image/png';
    } elseif ($ext === 'gif') {
    $logoMime = 'image/gif';
    } elseif ($ext === 'svg') {
    $logoMime = 'image/svg+xml';
    }
    $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
    }
    @endphp

    <div class="container">
        <!-- Encabezado -->
        <table class="header-table">
            <tr>
                <td style="width: 60%; vertical-align: middle;">
                    @if ($logoBase64)
                    <img style="height: 80px; margin-bottom: 5px; outline: none; border: none;" src="{{ $logoBase64 }}">
                    @else
                    <div class="logo">LUXURY EVYS</div>
                    @endif
                    <div class="company-info" style="margin-top: 5px;">
                        <strong>{{ $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.') }}</strong><br>
                        RUC: {{ $sucursal->ruc ?? '1793192550001' }}<br>
                        Dirección: {{ $sucursal->address ?? 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C' }}<br>
                        Teléfono: {{ $sucursal->phone ?? '0999179988 / 0963089601' }}<br>
                        Email: {{ $sucursal->email ?? 'comp.luxuryevys@gmail.com' }}
                    </div>
                </td>
                <td style="width: 40%; vertical-align: middle;">
                    <div class="document-title-card">
                        <h2>COTIZACIÓN</h2>
                        <div class="number">#{{ $quote->document_number }}</div>
                        <div style="margin-top: 5px; font-size: 11px; color: #666666;">
                            Fecha: {{ \Carbon\Carbon::parse($quote->service_date)->format('d/m/Y') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Datos del Cliente y Vehículo -->
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 49%; vertical-align: top; padding: 0 10px 0 0;">
                    <div class="section-title">Información del Cliente</div>
                    <div class="info-block">
                        <table class="info-grid">
                            <tr>
                                <td class="info-label">Cliente:</td>
                                <td class="info-value">{{ $quote->client->full_name ?? $quote->client->name }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Documento:</td>
                                <td class="info-value">{{ $quote->client->n_document ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Teléfono:</td>
                                <td class="info-value">{{ $quote->client->phone ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Email:</td>
                                <td class="info-value">{{ $quote->client->email ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width: 49%; vertical-align: top; padding: 0 0 0 10px;">
                    <div class="section-title">Información del Vehículo</div>
                    <div class="info-block">
                        <table class="info-grid">
                            @if($quote->vehicle)
                            <tr>
                                <td class="info-label">Placa:</td>
                                <td class="info-value" style="text-transform: uppercase; font-weight: bold;">{{ $quote->vehicle->license_plate }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Vehículo:</td>
                                <td class="info-value">
                                    @php
                                    $brandName = '';
                                    if ($quote->vehicle->brand) {
                                    $brandId = is_object($quote->vehicle->brand) ? $quote->vehicle->brand->id : $quote->vehicle->brand;
                                    $vehicleBrands = config('vehicle_brands', []);
                                    $brandName = $vehicleBrands[$brandId] ?? (is_object($quote->vehicle->brand) ? $quote->vehicle->brand->name : $quote->vehicle->brand);
                                    }
                                    @endphp
                                    {{ $brandName }} {{ $quote->vehicle->model ?? '' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="info-label">Año/Color:</td>
                                <td class="info-value">{{ $quote->vehicle->year ?? '-' }} / {{ $quote->vehicle->color ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Kilometraje:</td>
                                <td class="info-value">{{ $quote->mileage ? number_format($quote->mileage) . ' km' : '-' }}</td>
                            </tr>
                            @else
                            <tr>
                                <td colspan="2" style="text-align: center; color: #9ca3af; padding: 20px 0;">
                                    Sin vehículo asociado
                                </td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Tabla de Items -->
        <div class="section-title">Detalle de Servicios y Repuestos</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%;">Descripción</th>
                    <th style="width: 10%; text-align: center;">Cant.</th>
                    <th style="width: 15%; text-align: right;">PVP (Sin IVA)</th>
                    <th style="width: 10%; text-align: right;">Desc.</th>
                    <th style="width: 10%; text-align: right;">IVA (15%)</th>
                    <th style="width: 10%; text-align: right;">Total (Con IVA)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->details as $detail)
                @php
                // Precios unitarios y totales con IVA incluido en BD, mostramos base sin IVA
                $displayPrice = $detail->price / 1.15;
                $displayDiscount = ($detail->discount ?? 0) / 1.15;
                $displaySubtotalNeto = ($displayPrice - $displayDiscount) * $detail->quantity;
                $displayIva = $displaySubtotalNeto * 0.15;
                $displayTotal = $displaySubtotalNeto + $displayIva;
                @endphp
                <tr>
                    <td>
                        {{ $detail->description }}
                        @if($detail->product && $detail->product->sku)
                        <br><small style="color: #64748b; font-size: 10px;">SKU: {{ $detail->product->sku }}</small>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $detail->quantity }}</td>
                    <td style="text-align: right;">${{ number_format($displayPrice, 2) }}</td>
                    <td style="text-align: right;">
                        @if($detail->discount > 0)
                        -${{ number_format($displayDiscount, 2) }}
                        @else
                        -
                        @endif
                    </td>
                    <td style="text-align: right;">${{ number_format($displayIva, 2) }}</td>
                    <td style="text-align: right; font-weight: bold;">${{ number_format($displayTotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totales -->
        <table class="totals-table">
            @php
            $grossSubtotal = $quote->details->sum(function ($item) {
            return $item->quantity * ($item->price / 1.15);
            });
            $totalDiscount = $quote->details->sum(function ($item) {
            return ($item->discount ?? 0) / 1.15;
            });
            $netSubtotal = $grossSubtotal - $totalDiscount;
            $totalVal = $quote->details->sum(function ($item) {
            return ($item->price - ($item->discount ?? 0)) * $item->quantity;
            });
            $ivaVal = $totalVal - $netSubtotal;
            @endphp
            <tr>
                <td class="totals-label">Subtotal:</td>
                <td class="totals-value">${{ number_format($grossSubtotal, 2) }}</td>
            </tr>
            @if($totalDiscount > 0)
            <tr>
                <td class="totals-label">Descuento:</td>
                <td class="totals-value">-${{ number_format($totalDiscount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="totals-label">IVA (15%):</td>
                <td class="totals-value">${{ number_format($ivaVal, 2) }}</td>
            </tr>
            <tr class="grand-total-row">
                <td class="totals-label" style="font-size: 14px;">Total con IVA:</td>
                <td class="totals-value" style="font-size: 14px;">${{ number_format($totalVal, 2) }}</td>
            </tr>
        </table>

        <!-- Observaciones -->
        @if($quote->observations)
        <div class="observations-block">
            <div class="observations-title">Observaciones / Condiciones del Presupuesto</div>
            <div style="color: #4b5563; font-size: 12px; line-height: 1.4;">
                {!! nl2br(e($quote->observations)) !!}
            </div>
        </div>
        @endif

        <!-- Pie de página -->
        <div class="footer">
            Este presupuesto tiene una validez de 15 días a partir de la fecha de emisión.<br>
            ¡Gracias por confiar en Luxury Evys!
        </div>
    </div>
</body>

</html>
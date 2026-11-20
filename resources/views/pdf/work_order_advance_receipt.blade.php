<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Abono - {{ $advance->receipt_number }}</title>
    <style>
        @page {
            margin: 4mm 5mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #94a3b8;
            margin: 6px 0;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .header h1 {
            font-size: 13px;
            font-weight: 800;
            margin: 0 0 2px 0;
            color: #0f172a;
            text-transform: uppercase;
        }
        .header p {
            margin: 1px 0;
            font-size: 8px;
            color: #475569;
        }
        .badge-title {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 3px 0;
            margin: 5px 0;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 6px;
        }
        .info-table td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .info-label {
            color: #64748b;
            font-size: 8px;
            width: 35%;
        }
        .info-val {
            font-weight: bold;
            color: #0f172a;
            font-size: 8.5px;
        }
        .highlight-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px;
            margin: 6px 0;
            text-align: center;
        }
        .amount-large {
            font-size: 14px;
            font-weight: 900;
            color: #059669;
            margin: 2px 0;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .summary-table td {
            padding: 2px 0;
            font-size: 8.5px;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 7.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $tradeName = $sucursal->trade_name ?? '';
        $companyName = $sucursal->name ?? ($sucursal->company_name ?? ($sucursal->razon_social ?? 'LUXURY EVYS CIA. LTDA.'));
        $ruc = $sucursal->ruc ?? '1793192550001';
        $address = $sucursal->address ?? ($sucursal->direccion_matriz ?? 'SUR DE QUITO SECTOR EL BEATERIO S49B Y E1C');
        $phone = $sucursal->phone ?? ($sucursal->telefono ?? '');
        $email = $sucursal->email ?? '';

        // Cargar logo en base64 para evitar errores de renderizado en dompdf
        $logoBase64 = null;
        if ($sucursal && $sucursal->logo) {
            $cleanLogo = str_replace('storage/', '', ltrim($sucursal->logo, '/'));
            $candidates = [
                storage_path('app/public/' . $cleanLogo),
                public_path($sucursal->logo),
                public_path('storage/' . $cleanLogo),
                public_path('assets/img/brand/logo.png'),
                public_path('logo.png'),
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $type = pathinfo($cand, PATHINFO_EXTENSION);
                    $bytes = @file_get_contents($cand);
                    if ($bytes) {
                        $logoBase64 = 'data:image/' . ($type === 'svg' ? 'svg+xml' : $type) . ';base64,' . base64_encode($bytes);
                    }
                    break;
                }
            }
        }
    @endphp

    <div class="header">
        @if(!empty($logoBase64))
            <div style="text-align: center; margin-bottom: 3px;">
                <img src="{{ $logoBase64 }}" alt="Logo" style="max-height: 42px; max-width: 130px; object-fit: contain;">
            </div>
        @endif

        <h1>{{ !empty($tradeName) ? $tradeName : $companyName }}</h1>
        @if(!empty($tradeName) && !empty($companyName) && strtoupper(trim($tradeName)) !== strtoupper(trim($companyName)))
            <p style="font-size: 8.5px; font-weight: bold; color: #1e293b; margin: 1px 0;">{{ $companyName }}</p>
        @endif

        @if(!empty($ruc))
            <p><strong>RUC:</strong> {{ $ruc }}</p>
        @endif
        @if(!empty($address))
            <p>{{ $address }}</p>
        @endif
        @if(!empty($phone) || !empty($email))
            <p>
                @if(!empty($phone)) Telf.: {{ $phone }} @endif
                @if(!empty($phone) && !empty($email)) &nbsp;|&nbsp; @endif
                @if(!empty($email)) {{ $email }} @endif
            </p>
        @endif
    </div>

    <div class="badge-title">
        RECIBO DE ANTICIPO / ABONO
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">N° Recibo:</td>
            <td class="info-val">{{ $advance->receipt_number }}</td>
        </tr>
        <tr>
            <td class="info-label">Orden Trabajo:</td>
            <td class="info-val font-bold" style="color: #2563eb;">{{ $workOrder->number }}</td>
        </tr>
        <tr>
            <td class="info-label">Fecha:</td>
            <td class="info-val">{{ $dateFormatted }}</td>
        </tr>
        <tr>
            <td class="info-label">Cliente:</td>
            <td class="info-val">{{ $workOrder->client->full_name ?? ($workOrder->client->name ?? 'Cliente') }}</td>
        </tr>
        @if(!empty($workOrder->client->n_document))
        <tr>
            <td class="info-label">C.I. / RUC:</td>
            <td class="info-val">{{ $workOrder->client->n_document }}</td>
        </tr>
        @endif
        @if($workOrder->vehicle)
        <tr>
            <td class="info-label">Vehículo:</td>
            <td class="info-val">
                {{ $workOrder->vehicle->license_plate ?? 'S/P' }} - {{ $workOrder->vehicle->brand ?? '' }} {{ $workOrder->vehicle->model ?? '' }}
            </td>
        </tr>
        @endif
        <tr>
            <td class="info-label">Forma Pago:</td>
            <td class="info-val">{{ $advance->payment_method }} ({{ $advance->account->name ?? 'Caja' }})</td>
        </tr>
        @if(!empty($advance->notes))
        <tr>
            <td class="info-label">Detalle:</td>
            <td class="info-val">{{ $advance->notes }}</td>
        </tr>
        @endif
    </table>

    <div class="highlight-box">
        <div style="font-size: 8px; color: #475569; text-transform: uppercase;">MONTO ABONADO HOY</div>
        <div class="amount-large">${{ number_format($advance->amount, 2) }}</div>
    </div>

    <div class="divider"></div>

    <table class="summary-table">
        <tr>
            <td class="text-left" style="color: #64748b;">Total Presupuesto OT:</td>
            <td class="text-right font-bold">${{ number_format($totalAmount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-left" style="color: #059669;">Total Abonos Acumulados:</td>
            <td class="text-right font-bold" style="color: #059669;">-${{ number_format($totalAdvances, 2) }}</td>
        </tr>
        <tr style="border-top: 1px solid #cbd5e1;">
            <td class="text-left font-bold" style="font-size: 9.5px; padding-top: 4px;">SALDO PENDIENTE:</td>
            <td class="text-right font-bold" style="font-size: 10px; color: #dc2626; padding-top: 4px;">
                ${{ number_format($balanceDue, 2) }}
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer">
        <p>Este documento es un comprobante interno de abono y respaldo de entrega de valores a cuenta de la orden de trabajo señalada.</p>
        <p>¡Gracias por su confianza!</p>
        <div style="margin-top: 20px; border-top: 1px solid #94a3b8; width: 60%; margin-left: auto; margin-right: auto; padding-top: 2px;">
            Firma / Sello de Recepción
        </div>
    </div>
</body>
</html>

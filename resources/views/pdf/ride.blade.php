<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>RIDE - Factura Electrónica {{ $sale->document_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            width: 100%;
            padding: 12px 18px;
        }

        /* ── ENCABEZADO ─────────────────────────────────────────── */
        .header {
            width: 100%;
            border: 1px solid #999;
            margin-bottom: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .header-logo {
            width: 28%;
            border-right: 1px solid #999;
            text-align: center;
        }

        .header-logo img {
            max-height: 60px;
            max-width: 110px;
        }

        .header-logo .empresa-nombre {
            font-size: 10px;
            font-weight: bold;
            margin-top: 4px;
            color: #222;
        }

        .header-info {
            width: 42%;
            border-right: 1px solid #999;
            font-size: 8.5px;
            line-height: 1.6;
        }

        .header-info .label {
            font-weight: bold;
        }

        .header-doc {
            width: 30%;
            text-align: center;
            font-size: 8.5px;
        }

        .header-doc .doc-tipo {
            font-weight: bold;
            font-size: 11px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .header-doc .doc-numero {
            font-size: 10px;
            font-weight: bold;
            color: #333;
        }

        .header-doc .doc-clave {
            font-size: 7px;
            word-break: break-all;
            margin-top: 5px;
            border: 1px solid #ccc;
            padding: 3px;
            background: #f7f7f7;
        }

        /* ── AUTORIZACIÓN ───────────────────────────────────────── */
        .autorizacion-box {
            border: 1.5px solid #2e7d32;
            background: #f1f8e9;
            padding: 5px 8px;
            margin-bottom: 6px;
            border-radius: 2px;
        }

        .autorizacion-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .autorizacion-box .estado {
            font-weight: bold;
            font-size: 10px;
            color: #2e7d32;
        }

        /* ── RECEPTOR ───────────────────────────────────────────── */
        .receptor-box {
            border: 1px solid #aaa;
            margin-bottom: 6px;
        }

        .receptor-box .section-title {
            background: #455a64;
            color: #fff;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .receptor-box table {
            width: 100%;
            border-collapse: collapse;
            padding: 4px 8px;
        }

        .receptor-box table td {
            padding: 2px 8px;
            font-size: 8.5px;
        }

        .field-label {
            font-weight: bold;
            color: #444;
            width: 120px;
        }

        /* ── DETALLES ────────────────────────────────────────────── */
        .detalles-box {
            border: 1px solid #aaa;
            margin-bottom: 6px;
        }

        .detalles-box .section-title {
            background: #455a64;
            color: #fff;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detalles-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detalles-table thead th {
            background: #eceff1;
            border-bottom: 1px solid #bbb;
            padding: 4px 6px;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            color: #333;
        }

        .detalles-table tbody td {
            padding: 3px 6px;
            border-bottom: 1px solid #e8e8e8;
            font-size: 8.5px;
            vertical-align: top;
        }

        .detalles-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* ── TOTALES ─────────────────────────────────────────────── */
        .totales-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .totales-section td {
            vertical-align: top;
            padding: 0 4px;
        }

        .totales-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #aaa;
        }

        .totales-table .row {
            border-bottom: 1px solid #eee;
        }

        .totales-table .row td {
            padding: 3px 8px;
            font-size: 8.5px;
        }

        .totales-table .row .t-label {
            font-weight: bold;
            color: #444;
        }

        .totales-table .row .t-value {
            text-align: right;
            font-weight: bold;
        }

        .totales-table .row-total td {
            background: #455a64;
            color: #fff;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: bold;
        }

        /* ── INFORMACIÓN ADICIONAL ──────────────────────────────── */
        .info-adicional-box {
            border: 1px solid #aaa;
            margin-bottom: 6px;
        }

        .info-adicional-box .section-title {
            background: #455a64;
            color: #fff;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
        }

        .info-adicional-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-adicional-box table td {
            padding: 2px 8px;
            font-size: 8.5px;
        }

        /* ── FOOTER ─────────────────────────────────────────────── */
        .footer {
            text-align: center;
            font-size: 7.5px;
            color: #888;
            margin-top: 8px;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }
    </style>
</head>

<body>
    <div class="page">

        {{-- ═══ ENCABEZADO ════════════════════════════════════════════════ --}}
        <div class="header">
            <table class="header-table">
                <tr>
                    {{-- Logo + Nombre --}}
                    <td class="header-logo">
                        @if ($sucursal->logo && file_exists(storage_path('app/' . $sucursal->logo)))
                            <img src="{{ storage_path('app/public/' . ltrim($sucursal->logo, '/')) }}" alt="Logo">
                        @endif
                        <div class="empresa-nombre">{{ $sucursal->trade_name ?? $sucursal->name }}</div>
                        <div style="font-size:8px; color:#666;">{{ $sucursal->name }}</div>
                    </td>

                    {{-- Datos del Emisor --}}
                    <td class="header-info">
                        <div><span class="label">RUC:</span> {{ $sucursal->ruc }}</div>
                        <div><span class="label">Dirección Matriz:</span> {{ $sucursal->address }}</div>
                        <div><span class="label">Teléfono:</span> {{ $sucursal->phone }}</div>
                        <div><span class="label">Email:</span> {{ $sucursal->email }}</div>
                        <div><span class="label">Oblig. Contabilidad:</span>
                            {{ strtoupper($sucursal->obligado_contabilidad ?? 'NO') }}</div>
                        @if ($sucursal->contribuyente_especial)
                            <div><span class="label">Contribuyente Especial Nro.:</span>
                                {{ $sucursal->contribuyente_especial }}</div>
                        @endif
                    </td>

                    {{-- Tipo y Número de Documento --}}
                    <td class="header-doc">
                        <div class="doc-tipo">FACTURA</div>
                        <div class="doc-numero">{{ $sale->document_number }}</div>
                        <div style="font-size:7.5px; margin-top:4px; color:#555;">
                            <strong>Ambiente:</strong>
                            {{ env('SRI_AMBIENTE', 1) == 1 ? 'PRUEBAS' : 'PRODUCCIÓN' }}
                        </div>
                        <div style="font-size:7.5px; color:#555;">
                            <strong>Emisión:</strong> NORMAL
                        </div>
                        <div class="doc-clave">
                            <strong>CLAVE DE ACCESO</strong><br>
                            {{ $sale->sri_access_key }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ═══ ESTADO DE AUTORIZACIÓN ═════════════════════════════════════ --}}
        @if ($sale->sri_status === 'AUTORIZADA')
            <div class="autorizacion-box">
                <table>
                    <tr>
                        <td>
                            <span class="estado">✔ AUTORIZADO POR EL SRI</span>
                        </td>
                        <td style="text-align:right;">
                            <strong>Fecha de Autorización:</strong>
                            {{ $autorizacion['fechaAutorizacion'] ?? ($sale->sri_authorization_date ? $sale->sri_authorization_date->format('d/m/Y H:i:s') : '-') }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size:8px; color:#555; padding-top:2px;">
                            <strong>Nro. Autorización:</strong>
                            {{ $autorizacion['numeroAutorizacion'] ?? $sale->sri_access_key }}
                        </td>
                    </tr>
                </table>
            </div>
        @else
            <div
                style="border:1px solid #e57373; background:#ffebee; padding:4px 8px; margin-bottom:6px; font-size:8.5px;">
                <strong style="color:#c62828;">Estado SRI:</strong> {{ $sale->sri_status ?? 'PENDIENTE' }}
                @if ($sale->sri_error)
                    — {{ $sale->sri_error }}
                @endif
            </div>
        @endif

        {{-- ═══ DATOS DEL RECEPTOR ═════════════════════════════════════════ --}}
        <div class="receptor-box">
            <div class="section-title">Datos del Receptor</div>
            <table>
                <tr>
                    <td class="field-label">Razón Social / Nombres:</td>
                    <td>{{ $sale->client->full_name ?? $sale->client->name }}</td>
                    <td class="field-label">Identificación:</td>
                    <td>{{ $sale->client->n_document }}</td>
                </tr>
                <tr>
                    <td class="field-label">Tipo Identificación:</td>
                    <td>
                        @switch(strtolower($sale->client->type_document ?? ''))
                            @case('ruc')
                                RUC
                            @break

                            @case('pasaporte')
                                Pasaporte
                            @break

                            @default
                                Cédula de Identidad
                        @endswitch
                    </td>
                    <td class="field-label">Fecha Emisión:</td>
                    <td>
                        {{ $sale->service_date ? \Carbon\Carbon::parse($sale->service_date)->format('d/m/Y') : now()->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Dirección:</td>
                    <td colspan="3">{{ $sale->client->address ?? 'N/A' }}</td>
                </tr>
                @if ($sale->vehicle)
                    <tr>
                        <td class="field-label">Vehículo:</td>
                        <td>{{ $sale->vehicle->brand ?? '' }} {{ $sale->vehicle->model ?? '' }} — Placa:
                            {{ $sale->vehicle->license_plate ?? 'N/A' }}</td>
                        @if ($sale->mileage)
                            <td class="field-label">Kilometraje:</td>
                            <td>{{ number_format($sale->mileage) }} km</td>
                        @else
                            <td colspan="2"></td>
                        @endif
                    </tr>
                @endif
            </table>
        </div>

        {{-- ═══ DETALLES ════════════════════════════════════════════════════ --}}
        <div class="detalles-box">
            <div class="section-title">Detalle de Productos / Servicios</div>
            <table class="detalles-table">
                <thead>
                    <tr>
                        <th style="width:8%">Código</th>
                        <th style="width:36%; text-align:left;">Descripción</th>
                        <th style="width:8%">Cant.</th>
                        <th style="width:10%">P. Unit.</th>
                        <th style="width:8%">Desc.</th>
                        <th style="width:10%">Subtotal</th>
                        <th style="width:7%">IVA %</th>
                        <th style="width:13%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->details as $index => $detalle)
                        @php
                            $base = (float) $detalle->quantity * (float) $detalle->price - (float) $detalle->discount;
                            $taxValue = (float) $detalle->tax_value ?? $base * ((float) $detalle->tax_rate / 100);
                            $total = $base + $taxValue;
                        @endphp
                        <tr>
                            <td class="text-center">
                                {{ $detalle->product_id ? str_pad($detalle->product_id, 6, '0', STR_PAD_LEFT) : str_pad($index + 1, 6, '0', STR_PAD_LEFT) }}
                            </td>
                            <td>{{ $detalle->description }}</td>
                            <td class="text-center">{{ number_format((float) $detalle->quantity, 2) }}</td>
                            <td class="text-right">${{ number_format((float) $detalle->price, 2) }}</td>
                            <td class="text-right">${{ number_format((float) $detalle->discount, 2) }}</td>
                            <td class="text-right">${{ number_format($base, 2) }}</td>
                            <td class="text-center">{{ number_format((float) $detalle->tax_rate, 0) }}%</td>
                            <td class="text-right">${{ number_format($total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ═══ TOTALES ════════════════════════════════════════════════════ --}}
        <table class="totales-section">
            <tr>
                {{-- Información de Pago --}}
                <td style="width:55%;">
                    <div style="border:1px solid #aaa; font-size:8.5px;">
                        <div class="section-title"
                            style="background:#455a64; color:#fff; padding:3px 8px; font-weight:bold; font-size:8.5px; text-transform:uppercase;">
                            Forma de Pago
                        </div>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th style="padding:3px 8px; background:#eceff1; text-align:left; font-size:8px;">
                                        Forma de Pago</th>
                                    <th style="padding:3px 8px; background:#eceff1; text-align:right; font-size:8px;">
                                        Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $ridePayments = collect();
                                    if (isset($sale->financeRecord) && $sale->financeRecord->paymentDistributions->count() > 0) {
                                        $ridePayments = $sale->financeRecord->paymentDistributions;
                                    }
                                @endphp
                                @if ($ridePayments->isNotEmpty())
                                    @foreach ($ridePayments as $pDist)
                                        @php
                                            $pMethod = ucfirst(strtolower($pDist->payment_method ?? 'Efectivo'));
                                            $bName = '';
                                            if (isset($pDist->account) && !empty($pDist->account->bank_name)) {
                                                $bName = $pDist->account->bank_name;
                                            } elseif (isset($pDist->account) && !empty($pDist->account->name) && $pDist->account->type === 'bank') {
                                                $bName = $pDist->account->name;
                                            } elseif (isset($pDist->account_id)) {
                                                $acc = \App\Models\Finance\Account::find($pDist->account_id);
                                                if ($acc) {
                                                    $bName = $acc->bank_name ?? ($acc->type === 'bank' ? $acc->name : '');
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td style="padding:3px 8px;">
                                                {{ $pMethod }}
                                                @if (!empty($bName))
                                                    <br><span style="font-size: 6.5px; color: #777; font-weight: normal; text-transform: uppercase;">{{ $bName }}</span>
                                                @endif
                                            </td>
                                            <td style="padding:3px 8px; text-align:right; font-weight:bold;">
                                                ${{ number_format((float) $pDist->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td style="padding:3px 8px;">
                                            @php
                                                $rawMethod = strtolower($sale->payment_method ?? 'efectivo');
                                                $pMethod = match($rawMethod) {
                                                    'cash' => 'Efectivo',
                                                    'transfer' => 'Transferencia',
                                                    'card' => 'Tarjeta',
                                                    'credit' => 'Crédito',
                                                    default => ucfirst($sale->payment_method ?? 'Efectivo'),
                                                };
                                                $bName = '';
                                                if ($rawMethod === 'transferencia' || $rawMethod === 'transfer') {
                                                    $mov = \Illuminate\Support\Facades\DB::table('financial_movements')
                                                        ->where('movable_type', 'App\\Models\\Sales\\Sale')
                                                        ->where('movable_id', $sale->id)
                                                        ->first();
                                                    if ($mov && $mov->account_id) {
                                                        $acc = \App\Models\Finance\Account::find($mov->account_id);
                                                        if ($acc) {
                                                            $bName = $acc->bank_name ?? ($acc->type === 'bank' ? $acc->name : '');
                                                        }
                                                    }
                                                }
                                            @endphp
                                            {{ $pMethod }}
                                            @if (!empty($bName))
                                                <br><span style="font-size: 6.5px; color: #777; font-weight: normal; text-transform: uppercase;">{{ $bName }}</span>
                                            @endif
                                        </td>
                                        <td style="padding:3px 8px; text-align:right; font-weight:bold;">
                                            ${{ number_format((float) $sale->total, 2) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($sale->observations)
                        <div style="border:1px solid #ddd; margin-top:5px; font-size:8px; padding:4px 8px;">
                            <strong>Observaciones:</strong> {{ $sale->observations }}
                        </div>
                    @endif
                </td>

                {{-- Resumen de Totales --}}
                <td style="width:5%;"></td>
                <td style="width:40%; vertical-align:top;">
                    <table class="totales-table">
                        @if ((float) $sale->subtotal_iva_0 > 0)
                            <tr class="row">
                                <td class="t-label">Subtotal IVA 0%</td>
                                <td class="t-value">${{ number_format((float) $sale->subtotal_iva_0, 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $sale->subtotal_iva_15 > 0)
                            <tr class="row">
                                <td class="t-label">Subtotal IVA 15%</td>
                                <td class="t-value">${{ number_format((float) $sale->subtotal_iva_15, 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $sale->subtotal_no_objeto > 0)
                            <tr class="row">
                                <td class="t-label">No Objeto de IVA</td>
                                <td class="t-value">${{ number_format((float) $sale->subtotal_no_objeto, 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $sale->subtotal_exento > 0)
                            <tr class="row">
                                <td class="t-label">Exento de IVA</td>
                                <td class="t-value">${{ number_format((float) $sale->subtotal_exento, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="row">
                            <td class="t-label">Subtotal sin Impuestos</td>
                            <td class="t-value">${{ number_format((float) $sale->subtotal, 2) }}</td>
                        </tr>
                        <tr class="row">
                            <td class="t-label">IVA ({{ number_format(15, 0) }}%)</td>
                            <td class="t-value">${{ number_format((float) $sale->tax_amount, 2) }}</td>
                        </tr>
                        @php
                            $descTotal = $sale->details->sum('discount');
                        @endphp
                        @if ($descTotal > 0)
                            <tr class="row">
                                <td class="t-label">Descuento Total</td>
                                <td class="t-value">-${{ number_format((float) $descTotal, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="row-total">
                            <td>IMPORTE TOTAL</td>
                            <td style="text-align:right;">${{ number_format((float) $sale->total, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ═══ INFORMACIÓN ADICIONAL ══════════════════════════════════════ --}}
        @if ($sale->client->email || $sale->client->phone)
            <div class="info-adicional-box">
                <div class="section-title">Información Adicional</div>
                <table>
                    @if ($sale->client->email)
                        <tr>
                            <td class="field-label">Email:</td>
                            <td>{{ $sale->client->email }}</td>
                        </tr>
                    @endif
                    @if ($sale->client->phone)
                        <tr>
                            <td class="field-label">Teléfono:</td>
                            <td>{{ $sale->client->phone }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        {{-- ═══ FOOTER ══════════════════════════════════════════════════════ --}}
        <div class="footer">
            Este documento es una Representación Impresa de un Comprobante Electrónico.
            Para verificar su validez, ingrese la clave de acceso en:
            <strong>https://sri.gob.ec</strong> &nbsp;|&nbsp;
            Generado: {{ now()->format('d/m/Y H:i') }}
        </div>

    </div>
</body>

</html>

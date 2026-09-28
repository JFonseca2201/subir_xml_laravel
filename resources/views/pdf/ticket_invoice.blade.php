<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura Ticket - {{ $sale->document_number }}</title>
    <style>
        @page {
            size: A4 portrait; /* Hoja A4 en vertical */
            margin: 6mm 8mm 6mm 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, Consolas, 'Lucida Console', Monaco, monospace;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            background-color: #ffffff;
            color: #111111;
            font-size: 10px;
            line-height: 1.18;
            padding: 0;
            width: 100%;
            max-width: 195mm;
            margin: 0 auto;
        }

        /* ─── CONTENEDOR DE MEDIA HOJA A4 VERTICAL (EXACTO 135MM) ─── */
        .ticket-wrapper {
            position: relative;
            width: 100%;
            max-width: 100%;
            height: 135mm;
            max-height: 135mm;
            padding-right: 20px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        /* ─── BOTONES DE PANTALLA (NO IMPRIMIR) ─── */
        .no-print-toolbar {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(15, 23, 42, 0.9);
            padding: 8px 14px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .no-print-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .no-print-btn.btn-close {
            background: #475569;
        }

        .no-print-btn:hover {
            opacity: 0.9;
        }

        @media print {
            .no-print-toolbar {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
        }

        /* ─── HEADER PRINCIPAL ─── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-logo-col {
            width: 65px;
            padding-right: 8px;
        }

        .company-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            display: block;
        }

        .logo-monogram {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: 2px solid #222;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: -1px;
            color: #222;
        }

        .company-info-col {
            width: 53%;
            padding-right: 8px;
        }

        .company-name {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #000000;
            margin-bottom: 2px;
            line-height: 1.15;
        }

        .company-detail {
            font-size: 9.2px;
            color: #222222;
            line-height: 1.2;
        }

        .company-detail-bold {
            font-weight: bold;
        }

        .doc-header-col {
            width: 38%;
            text-align: right;
        }

        .doc-title-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 2px;
        }

        .doc-type-title {
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .doc-number {
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
        }

        .branch-tag {
            font-size: 11px;
            font-weight: 800;
            text-align: right;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        /* Mini tabla de pagos */
        .payment-status-grid {
            display: table;
            width: 100%;
            margin-top: 2px;
        }

        .payment-row {
            display: table-row;
        }

        .payment-lbl-left {
            display: table-cell;
            font-size: 8.8px;
            text-align: left;
            padding-right: 4px;
            color: #333;
        }

        .payment-lbl {
            display: table-cell;
            font-size: 9.2px;
            font-weight: bold;
            text-align: right;
            padding-right: 6px;
            width: 35px;
        }

        .payment-val {
            display: table-cell;
            font-size: 9.5px;
            text-align: right;
            font-weight: normal;
            width: 50px;
        }

        /* ─── BARRA DE AUTORIZACIÓN & CLAVE ─── */
        .auth-bar {
            width: 100%;
            margin-top: 2px;
            margin-bottom: 3px;
            font-size: 9.2px;
            line-height: 1.2;
            letter-spacing: 0.3px;
        }

        .auth-label {
            font-weight: bold;
        }

        /* ─── INFORMACIÓN DEL CLIENTE ─── */
        .customer-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 9.5px;
        }

        .customer-table td {
            vertical-align: top;
            padding: 0.5px 0;
        }

        .cust-left {
            width: 56%;
            padding-right: 6px;
        }

        .cust-right {
            width: 44%;
            text-align: left;
        }

        .cust-row {
            display: flex;
            margin-bottom: 1.5px;
        }

        .cust-label {
            font-weight: bold;
            min-width: 85px;
            color: #000;
        }

        .cust-label-sm {
            font-weight: bold;
            min-width: 65px;
            color: #000;
        }

        .cust-value {
            flex: 1;
            text-transform: uppercase;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sale-note-badge {
            font-size: 9.5px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0;
            text-transform: uppercase;
        }

        /* ─── TABLA DE ITEMS / DETALLE ─── */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 2px;
            margin-bottom: 4px;
            font-size: 9.2px;
        }

        .items-table th {
            font-weight: bold;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 2.5px 2px;
            text-transform: uppercase;
            font-size: 9px;
        }

        .items-table td {
            padding: 2px 2px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .col-code  { width: 15%; text-align: left !important; }
        .col-desc  { width: 45%; text-align: left !important; }
        .col-qty   { width: 10%; text-align: right !important; }
        .col-price { width: 10%; text-align: right !important; }
        .col-disc  { width: 10%; text-align: right !important; }
        .col-total { width: 10%; text-align: right !important; }

        /* ─── FOOTER (FIRMA, TOTALES - FIJADO AL CORTE INFERIOR DE 135MM) ─── */
        .footer-table {
            position: absolute;
            bottom: 0px;
            left: 0px;
            width: calc(100% - 20px);
            border-collapse: collapse;
            margin: 0;
        }

        .footer-table td {
            vertical-align: bottom;
        }

        .footer-signature-col {
            width: 45%;
            padding-right: 12px;
        }

        .signature-container {
            min-height: 48px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .signature-space {
            height: 32px;
            width: 100%;
        }

        .signature-line {
            width: 100%;
            border-bottom: 1.5px solid #000;
            margin-bottom: 3px;
        }

        .dispatch-text {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.2px;
        }

        .signature-caption {
            font-size: 7.5px;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }

        .footer-center-col {
            width: 15%;
        }

        .footer-totals-col {
            width: 40%;
            text-align: right;
            padding-left: 6px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            line-height: 1.25;
        }

        .totals-table td {
            padding: 0.5px 0;
        }

        .tot-label {
            text-align: right;
            font-weight: normal;
            color: #111;
            padding-right: 8px;
        }

        .tot-value {
            text-align: right;
            font-weight: normal;
            width: 58px;
            color: #000;
        }

        .tot-grand-label {
            font-weight: bold;
            text-align: right;
            padding-right: 8px;
        }

        .tot-grand-value {
            font-weight: bold;
            text-align: right;
        }

        /* ─── TEXTO LATERAL VERTICAL (MARGEN DERECHO - CONFINADO A LA MEDIA HOJA DE 135MM) ─── */
        .lateral-watermark {
            position: absolute;
            right: 0px;
            top: 0px;
            bottom: 0px;
            height: 135mm;
            max-height: 135mm;
            width: 14px;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6.5px;
            color: #777777;
            letter-spacing: 0.6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1;
        }

        /* ─── LÍNEA DE CORTE SIMPLE (SIN TEXTO) ─── */
        .cut-line {
            width: 100%;
            border-bottom: 1px dashed #9ca3af;
            margin-top: 3mm;
        }
    </style>
</head>
<body>

@php
    // Formatear número de documento
    $docNumber = $sale->document_number ?? '000000001';
    $establecimiento = $sucursal->establecimiento ?? '001';
    $puntoEmision = $sucursal->punto_emision ?? '001';
    
    if (strpos($docNumber, '-') !== false) {
        $formattedDocNumber = $docNumber;
    } else {
        $cleanNum = preg_replace('/[^0-9]/', '', $docNumber);
        $formattedDocNumber = sprintf('%s-%s-%s', $establecimiento, $puntoEmision, str_pad($cleanNum, 9, '0', STR_PAD_LEFT));
    }

    // Nombre sucursal / comercial
    $branchName = $sucursal->name ?? ($sucursal->trade_name ?? 'MATRIZ');
    if (preg_match('/BEATERIO/i', $branchName) || preg_match('/BEATERIO/i', $sucursal->address ?? '')) {
        $branchTag = 'BEATERIO';
    } else {
        $branchTag = $sucursal->trade_name ?? 'MATRIZ';
    }

    // Desglose de Pagos (EFE, TAR, CRE)
    $efeAmount = 0.00;
    $tarAmount = 0.00;
    $creAmount = 0.00;

    if ($sale->is_credited || $sale->payment_status === 'pending') {
        $creAmount = (float)$sale->total;
    } elseif ($sale->financeRecord && $sale->financeRecord->paymentDistributions && $sale->financeRecord->paymentDistributions->count() > 0) {
        foreach ($sale->financeRecord->paymentDistributions as $pd) {
            $method = strtolower($pd->payment_method ?? ($pd->account->type ?? ''));
            $accName = strtolower($pd->account->name ?? '');
            if (str_contains($method, 'cash') || str_contains($method, 'efectivo') || str_contains($accName, 'caja') || str_contains($accName, 'efectivo')) {
                $efeAmount += (float)$pd->amount;
            } elseif (str_contains($method, 'card') || str_contains($method, 'tarjeta') || str_contains($method, 'transf') || str_contains($method, 'banco') || str_contains($accName, 'banco') || str_contains($accName, 'tarjeta') || str_contains($accName, 'pichincha') || str_contains($accName, 'guayaquil') || str_contains($accName, 'produbanco')) {
                $tarAmount += (float)$pd->amount;
            } elseif (str_contains($method, 'credit') || str_contains($method, 'credito') || str_contains($accName, 'credito')) {
                $creAmount += (float)$pd->amount;
            } else {
                $tarAmount += (float)$pd->amount;
            }
        }
    } else {
        $method = strtolower($sale->payment_method ?? 'efectivo');
        if (str_contains($method, 'tarjeta') || str_contains($method, 'transf') || str_contains($method, 'card') || str_contains($method, 'banco')) {
            $tarAmount = (float)$sale->total;
        } elseif (str_contains($method, 'cred') || $sale->payment_status === 'pending') {
            $creAmount = (float)$sale->total;
        } else {
            $efeAmount = (float)$sale->total;
        }
    }

    // Logo en Base64
    $logoBase64 = null;
    $logoPath = null;
    if ($sucursal && $sucursal->logo) {
        $cleanLogo = str_replace('storage/', '', $sucursal->logo);
        $tempPath = storage_path('app/public/' . $cleanLogo);
        if (file_exists($tempPath)) {
            $logoPath = $tempPath;
        } elseif (file_exists(public_path($sucursal->logo))) {
            $logoPath = public_path($sucursal->logo);
        }
    }
    if (!$logoPath || !file_exists($logoPath)) {
        if (file_exists(public_path('assets/img/brand/logo.jpeg'))) {
            $logoPath = public_path('assets/img/brand/logo.jpeg');
        } elseif (file_exists(public_path('assets/img/brand/logo.png'))) {
            $logoPath = public_path('assets/img/brand/logo.png');
        }
    }
    if ($logoPath && file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'svg') ? 'image/svg+xml' : 'image/jpeg');
        $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoData);
    }

    // Cálculos de Totales
    $subtotal = (float)($sale->subtotal ?? 0);
    $subtotal0 = (float)($sale->subtotal_iva_0 ?? 0);
    $subtotal15 = (float)($sale->subtotal_iva_15 ?? 0);
    $taxAmount = (float)($sale->tax_amount ?? 0);
    $totalAmount = (float)($sale->total ?? 0);
    
    // Descuentos totales sumados
    $totalDiscount = 0.00;
    foreach ($sale->details as $d) {
        $totalDiscount += (float)($d->discount ?? 0);
    }
    if ($totalDiscount == 0 && (float)$sale->discount_amount > 0) {
        $totalDiscount = (float)$sale->discount_amount;
    }

    // Clave de acceso
    $claveAcceso = $sale->sri_access_key ?? ($autorizacion['numeroAutorizacion'] ?? '');
    
    // Nombre del cliente
    $clientName = $sale->client->full_name ?? trim(($sale->client->name ?? '') . ' ' . ($sale->client->surname ?? ''));
    if (empty($clientName)) {
        $clientName = 'CONSUMIDOR FINAL';
    }

    // Nombre de la empresa
    $companyName = $sucursal->trade_name ?? ($sucursal->name ?? 'LUXURY EVYS CIA. LTDA.');
@endphp

<!-- Barra de control en pantalla -->
<div class="no-print-toolbar">
    <button class="no-print-btn" onclick="window.print()">
        🖨️ Imprimir Factura
    </button>
    <button class="no-print-btn btn-close" onclick="window.close()">
        ✕ Cerrar
    </button>
</div>

<div class="ticket-wrapper">
    <!-- ─── 1. HEADER (EMPRESA + DATOS DE FACTURA) ─── -->
    <table class="header-table">
        <tr>
            <!-- Logo -->
            <td class="company-logo-col">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo" class="company-logo" />
                @else
                    <div class="logo-monogram">
                        <span>AJ</span>
                    </div>
                @endif
            </td>

            <!-- Info Empresa -->
            <td class="company-info-col">
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-detail"><span class="company-detail-bold">RUC:</span> {{ $sucursal->ruc ?? '1793192550001' }}</div>
                <div class="company-detail"><span class="company-detail-bold">{{ $branchTag }}:</span> {{ $sucursal->address ?? 'SUR DE QUITO SECTOR EL BEATERIO S49B Y E1C' }}</div>
                <div class="company-detail">Telf.: {{ $sucursal->phone ?? '0999179988' }} - E-mail: {{ $sucursal->email ?? 'comp.luxueryevys@gmail.com' }}</div>
                <div class="company-detail" style="font-weight: bold; margin-top: 1px;">
                    {{ (isset($sucursal->obligado_contabilidad) && in_array(strtoupper($sucursal->obligado_contabilidad), ['SI', '1', 'TRUE'])) ? '"OBLIGADO A LLEVAR CONTABILIDAD"' : '"NO OBLIGADO A LLEVAR CONTABILIDAD"' }}
                </div>
                @if($sucursal && $sucursal->contribuyente_especial)
                    <div class="company-detail" style="font-size: 7.8px;">CONTRIBUYENTE ESPECIAL SEGÚN RESOLUCIÓN NRO. {{ $sucursal->contribuyente_especial }}</div>
                @else
                    <div class="company-detail" style="font-size: 7.8px; opacity: 0.9;">CONTRIBUYENTE RÉGIMEN GENERAL</div>
                @endif
            </td>

            <!-- Datos Factura & Pagos -->
            <td class="doc-header-col">
                <div class="doc-title-row">
                    <span class="doc-type-title">{{ $sale->document_type === 'quote' ? 'Cotización No.' : ($sale->document_type === 'note' ? 'Nota Venta No.' : 'Factura No.') }}</span>
                    <span class="doc-number">{{ $formattedDocNumber }}</span>
                </div>
                <div class="branch-tag">{{ $branchTag }}</div>

                <!-- Mini tabla de pagos (EFE, TAR, CRE) y Ambiente -->
                <div class="payment-status-grid">
                    <div class="payment-row">
                        <div class="payment-lbl-left"></div>
                        <div class="payment-lbl">EFE</div>
                        <div class="payment-val">{{ number_format($efeAmount, 2) }}</div>
                    </div>
                    <div class="payment-row">
                        <div class="payment-lbl-left">AMBIENTE: {{ ($sucursal->ambiente == '2' || config('sri.ambiente') == '2') ? 'PRODUCCIÓN' : 'PRUEBAS' }}</div>
                        <div class="payment-lbl">TAR</div>
                        <div class="payment-val">{{ number_format($tarAmount, 2) }}</div>
                    </div>
                    <div class="payment-row">
                        <div class="payment-lbl-left">EMISIÓN: NORMAL</div>
                        <div class="payment-lbl">CRE</div>
                        <div class="payment-val">{{ number_format($creAmount, 2) }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ─── 2. AUTORIZACIÓN & CLAVE DE ACCESO ─── -->
    <div class="auth-bar">
        <div><span class="auth-label">AUTORIZACIÓN:</span> &nbsp;{{ $claveAcceso ?: 'PENDIENTE DE AUTORIZACIÓN' }}</div>
        <div><span class="auth-label">CLAVE DE ACCESO:</span> {{ $claveAcceso }}</div>
    </div>

    <!-- ─── 3. DATOS DEL CLIENTE Y VENTA ─── -->
    <table class="customer-table">
        <tr>
            <td class="cust-left">
                <div class="cust-row">
                    <span class="cust-label">CLIENTE:</span>
                    <span class="cust-value">{{ $clientName }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label">DIRECCIÓN:</span>
                    <span class="cust-value">{{ $sale->client->address ?? 'S/N' }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label">CIUDAD:</span>
                    <span class="cust-value">{{ $sale->client->distrito ?? ($sale->client->provincia ?? 'QUITO') }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label">E-MAIL:</span>
                    <span class="cust-value" style="text-transform: none;">{{ $sale->client->email ?? '-' }}</span>
                </div>
            </td>
            <td class="cust-right">
                <div class="cust-row">
                    <span class="cust-label-sm">RUC:</span>
                    <span class="cust-value">{{ $sale->client->n_document ?? '9999999999999' }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label-sm">TELEFONO:</span>
                    <span class="cust-value">{{ $sale->client->phone ?? '-' }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label-sm">FECHA:</span>
                    <span class="cust-value">{{ $sale->created_at ? $sale->created_at->format('d/m/Y') : date('d/m/Y') }} &nbsp;&nbsp;&nbsp; {{ $sale->created_at ? $sale->created_at->format('H:i') : date('H:i') }}</span>
                </div>
                <div class="cust-row">
                    <span class="cust-label-sm">VENDEDOR:</span>
                    <span class="cust-value">{{ $sale->user->name ?? ($sale->user->full_name ?? 'Jonathan Prieto') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Etiqueta de Referencia / Venta / OT -->
    <div class="sale-note-badge">
        VENTA {{ strtoupper($sale->user->name ?? 'MOSTRADOR') }}
        @if($sale->work_order_number)
            - OT: {{ $sale->work_order_number }}
        @endif
        @if($sale->vehicle && $sale->vehicle->plate)
            - PLACA: {{ $sale->vehicle->plate }}
        @endif
    </div>

    <!-- ─── 4. TABLA DE DETALLE / PRODUCTOS ─── -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-code">CÓDIGO</th>
                <th class="col-desc">DETALLE</th>
                <th class="col-qty">CANTIDAD</th>
                <th class="col-price">PRECIO</th>
                <th class="col-disc">DESCUENTO</th>
                <th class="col-total">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->details as $item)
                @php
                    $code = $item->product->sku ?? ($item->product->code_aux ?? ($item->product->barcode ?? '-'));
                    $desc = $item->description ?? ($item->product->description ?? ($item->product->name ?? 'ITEM'));
                    $itemQty = (float)$item->quantity;
                    $itemPrice = (float)$item->price;
                    $itemDisc = (float)$item->discount;
                    $itemTot = (float)$item->total;
                @endphp
                <tr>
                    <td class="col-code">{{ $code }}</td>
                    <td class="col-desc">{{ $desc }}</td>
                    <td class="col-qty">{{ number_format($itemQty, 2) }}</td>
                    <td class="col-price">{{ number_format($itemPrice, 2) }}</td>
                    <td class="col-disc">{{ $itemDisc > 0 ? number_format($itemDisc, 2) : '' }}</td>
                    <td class="col-total">{{ number_format($itemTot, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ─── 5. FOOTER (FIRMA MANUAL, SELLO, TOTALES) ─── -->
    <table class="footer-table">
        <tr>
            <!-- Columna Izquierda: Espacio para Firma Manual y Comprobante de Despacho -->
            <td class="footer-signature-col">
                <div class="signature-container">
                    <!-- Espacio en blanco para la firma manual con esfero -->
                    <div class="signature-space"></div>
                    <div class="signature-line"></div>
                    <div class="dispatch-text">
                        COMPROBANTE DE DESPACHO &nbsp;{{ $formattedDocNumber }}
                    </div>
                    <div class="signature-caption">FIRMA CLIENTE / RECIBÍ CONFORME</div>
                </div>
            </td>

            <!-- Espacio Central Libre (para colocar sello físico) -->
            <td class="footer-center-col"></td>

            <!-- Columna Derecha: Totales SRI -->
            <td class="footer-totals-col">
                <table class="totals-table">
                    <tr>
                        <td class="tot-label">SUB TOTAL:</td>
                        <td class="tot-value">{{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-label">SUBTOTAL SIN IMPUESTOS:</td>
                        <td class="tot-value">{{ number_format($subtotal0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-label">DESCUENTOS:</td>
                        <td class="tot-value">{{ number_format($totalDiscount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-label">IVA 15%:</td>
                        <td class="tot-value">{{ number_format($taxAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-grand-label">VALOR TOTAL SRI:</td>
                        <td class="tot-grand-value">{{ number_format($totalAmount, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Marca de agua vertical en el borde derecho -->
    <div class="lateral-watermark">
        SISTEMA POS &amp; FACTURACIÓN ELECTRÓNICA SRI / 1x1 COPIA (V) / {{ $formattedDocNumber }}
    </div>
</div>

<!-- Línea de corte simple para separar la media hoja A4 -->
<div class="cut-line"></div>

<script>
    // Auto-disparar diálogo de impresión si se abre en ventana independiente
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            window.print();
        }, 400);
    });
</script>

</body>
</html>

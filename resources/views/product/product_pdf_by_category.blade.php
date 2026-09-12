<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Reporte de Productos por Categoría</title>
    <style>
        * {
            font-family: Helvetica, Arial, sans-serif !important;
            box-sizing: border-box;
        }

        @page {
            margin: 12mm 14mm 14mm 14mm;
            size: letter portrait;
        }

        body {
            font-family: Helvetica, Arial, sans-serif !important;
            font-size: 8.5px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.3;
            padding: 0;
            margin: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ── HEADER DE CABECERA ────────────────────────────────────────── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .logo-img {
            max-height: 60px;
            max-width: 200px;
            object-fit: contain;
            display: block;
        }

        .company-name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .company-detail {
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 1.5px;
        }

        .report-title-box {
            text-align: right;
        }

        .report-badge {
            display: inline-block;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .report-date {
            font-size: 8px;
            color: #64748b;
        }

        /* ── KPI METRICS BAR ───────────────────────────────────────────── */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .kpi-table td {
            padding: 5px 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            text-align: center;
        }

        .kpi-label {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }

        .kpi-val {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 1px;
        }

        /* ── BLOQUES POR CATEGORÍA ─────────────────────────────────────── */
        .category-block {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .category-header-table {
            width: 100%;
            border-collapse: collapse;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-radius: 4px 4px 0 0;
        }

        .category-header-table td {
            padding: 5px 8px;
            vertical-align: middle;
        }

        .category-title {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .category-count {
            font-size: 8px;
            font-weight: 700;
            color: #475569;
            text-align: right;
        }

        /* ── TABLA DE PRODUCTOS ────────────────────────────────────────── */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            border-top: none;
            margin-bottom: 2px;
        }

        .products-table thead th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 7.8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 6px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }

        .products-table tbody td {
            padding: 3.5px 6px;
            font-size: 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #1e293b;
        }

        .products-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .products-table tbody tr:last-child td {
            border-bottom: none;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        .sku-tag {
            font-weight: 700;
            color: #0f172a;
        }

        .stock-badge {
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 3px;
        }

        .stock-positive {
            color: #15803d;
        }

        .stock-zero {
            color: #b91c1c;
            font-weight: 700;
        }

        .price-text {
            font-weight: 700;
            color: #0f172a;
        }

        /* ── FOOTER ────────────────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -28px;
            left: 0;
            right: 0;
            height: 24px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 7px;
            color: #64748b;
            line-height: 1.2;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>

<body>
    @php
    // Cargar logo en base64 para renderizado perfecto en DomPDF
    $logoBase64 = '';
    $logoCandidates = [
        $sucursal && $sucursal->logo ? storage_path('app/public/' . str_replace('storage/', '', ltrim($sucursal->logo, '/'))) : null,
        $sucursal && $sucursal->logo ? public_path($sucursal->logo) : null,
        public_path('assets/img/brand/logo.png'),
        public_path('assets/img/brand/logo.jpeg'),
        public_path('logo.png'),
    ];
    foreach ($logoCandidates as $cand) {
        if ($cand && file_exists($cand) && filesize($cand) > 0) {
            $ext = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['png', 'gif', 'svg']) ? "image/{$ext}" : 'image/jpeg';
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($cand));
            break;
        }
    }
    @endphp

    <div class="container">
        {{-- ═══ CABECERA DEL REPORTE ════════════════════════════════════════ --}}
        <table class="header-table">
            <tr>
                {{-- LOGO O NOMBRE COMERCIAL --}}
                <td style="width: 50%;">
                    @if (!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo" style="margin-bottom: 4px;">
                    @endif
                    <div class="company-name">{{ $sucursal->trade_name ?? $sucursal->name ?? 'LUXURY EVYS CIA. LTDA.' }}</div>
                    <div class="company-detail"><strong>RUC:</strong> {{ $sucursal->ruc ?? '1793192550001' }}</div>
                    <div class="company-detail"><strong>Dirección:</strong> {{ $sucursal->address ?? 'SUR DE QUITO' }}</div>
                    @if(!empty($sucursal->phone))
                    <div class="company-detail"><strong>Teléfono:</strong> {{ $sucursal->phone }}</div>
                    @endif
                </td>

                {{-- TÍTULO Y FECHA DE EMISIÓN --}}
                <td style="width: 50%; text-align: right;" class="report-title-box">
                    <div class="report-badge">Inventario por Categoría</div>
                    <div class="report-date" style="margin-top: 4px;">
                        <strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y H:i:s') }}
                    </div>
                    @if(!empty($selectedWarehouse))
                    <div class="report-date">
                        <strong>Almacén/Bodega:</strong> {{ $selectedWarehouse->name }}
                    </div>
                    @endif
                    @if(!empty($selectedCategory))
                    <div class="report-date">
                        <strong>Categoría Filtrada:</strong> {{ $selectedCategory->title }}
                    </div>
                    @endif
                    @if(!empty($search))
                    <div class="report-date">
                        <strong>Búsqueda:</strong> "{{ $search }}"
                    </div>
                    @endif
                </td>
            </tr>
        </table>

        {{-- ═══ RESUMEN / MÉTRICAS GENERALES ════════════════════════════════ --}}
        <table class="kpi-table">
            <tr>
                <td style="width: 33.3%;">
                    <div class="kpi-label">Categorías Registradas</div>
                    <div class="kpi-val">{{ $totalCategories }}</div>
                </td>
                <td style="width: 33.3%;">
                    <div class="kpi-label">Total Productos</div>
                    <div class="kpi-val">{{ $totalProducts }}</div>
                </td>
                <td style="width: 33.4%;">
                    <div class="kpi-label">Stock Total en Unidades</div>
                    <div class="kpi-val">{{ number_format($totalStock, 2) }}</div>
                </td>
            </tr>
        </table>

        {{-- ═══ LISTADO DE PRODUCTOS AGRUPADOS POR CATEGORÍA ═══════════════ --}}
        @forelse ($groupedProducts as $categoryName => $catProducts)
        @php
            $catStockTotal = (float) $catProducts->sum('stock');
        @endphp
        <div class="category-block">
            {{-- Encabezado de Categoría --}}
            <table class="category-header-table">
                <tr>
                    <td class="category-title" style="width: 60%;">
                        {{ $categoryName }}
                    </td>
                    <td class="category-count" style="width: 40%;">
                        {{ $catProducts->count() }} producto(s) &nbsp;|&nbsp; 
                        Stock: <strong>{{ number_format($catStockTotal, 2) }}</strong>
                    </td>
                </tr>
            </table>

            {{-- Tabla de Productos de la Categoría --}}
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 16%; padding-left: 6px;">Código / SKU</th>
                        <th style="width: 38%;">Descripción</th>
                        <th style="width: 14%;">Marca</th>
                        <th style="width: 8%; text-align: center;">Unidad</th>
                        <th style="width: 10%; text-align: center;">Stock</th>
                        <th style="width: 14%; text-align: right; padding-right: 6px;">P. Venta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($catProducts as $product)
                    @php
                        $stockVal = (float)($product->stock ?? 0);
                        $priceSale = (float)($product->price_sale ?? $product->price ?? 0);
                        $unitName = $product->unit ? ($product->unit->code ?? $product->unit->name) : 'UND';
                    @endphp
                    <tr>
                        <td class="sku-tag" style="padding-left: 6px;">
                            {{ $product->sku ?: ($product->code ?: str_pad($product->id, 6, '0', STR_PAD_LEFT)) }}
                            @if(!empty($product->code_aux) && $product->code_aux !== $product->sku)
                            <div style="font-size: 7px; color: #64748b; font-weight: normal;">Aux: {{ $product->code_aux }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $product->description }}</div>
                            @if(!empty($product->uses))
                            <div style="font-size: 7.2px; color: #64748b;">{{ Str::limit($product->uses, 60) }}</div>
                            @endif
                        </td>
                        <td style="color: #475569; font-size: 7.8px; text-transform: uppercase;">
                            {{ $product->brand ?: '-' }}
                        </td>
                        <td class="text-center" style="color: #475569; font-size: 7.5px;">
                            {{ $unitName }}
                        </td>
                        <td class="text-center">
                            <span class="stock-badge {{ $stockVal > 0 ? 'stock-positive' : 'stock-zero' }}">
                                {{ number_format($stockVal, 2) }}
                            </span>
                        </td>
                        <td class="text-right price-text" style="padding-right: 6px;">
                            ${{ number_format($priceSale, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @empty
        <div style="text-align: center; padding: 30px 10px; color: #64748b; font-size: 11px; border: 1px dashed #cbd5e1; border-radius: 6px;">
            No se encontraron productos registrados con los filtros seleccionados.
        </div>
        @endforelse
    </div>

    {{-- ═══ PIE DE PÁGINA FIJO CON PAGINACIÓN ════════════════════════════ --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width: 70%; text-align: left;">
                    Reporte generado por el Sistema POS &nbsp;|&nbsp; {{ $sucursal->name ?? 'LUXURY EVYS' }}
                </td>
                <td style="width: 30%; text-align: right;">
                    {{-- DomPDF inyecta los números de página --}}
                </td>
            </tr>
        </table>
    </div>

    {{-- Script embebido en PHP para número de página exacto en DomPDF --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->get_font("Helvetica, Arial, sans-serif", "normal");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = $pdf->get_width() - 14 * 2.83465 - $width;
            $y = $pdf->get_height() - 10 * 2.83465;
            $pdf->page_text($x, $y, $text, $font, $size, array(100/255, 116/255, 139/255));
        }
    </script>
</body>

</html>

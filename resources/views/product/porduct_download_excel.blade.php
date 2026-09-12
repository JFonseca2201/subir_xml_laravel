<table>
    <thead>
        <!-- Título Principal del Reporte -->
        <tr>
            <th colspan="18" style="font-size: 15px; font-weight: bold; text-align: center; background-color: #0f172a; color: #ffffff; height: 35px; vertical-align: middle;">
                REPORTE DE INVENTARIO Y PRODUCTOS POR CATEGORÍA
            </th>
        </tr>
        <tr>
            <th colspan="18" style="font-size: 10px; text-align: center; background-color: #f1f5f9; color: #475569; height: 22px; vertical-align: middle;">
                Fecha de Emisión: {{ date('d/m/Y H:i:s') }} &nbsp;|&nbsp; Total Categorías: {{ count($groupedProducts) }} &nbsp;|&nbsp; Total Productos: {{ $totalProducts }} &nbsp;|&nbsp; Stock Total: {{ number_format($totalStock, 2) }}
            </th>
        </tr>
        <tr>
            <th colspan="18" style="height: 10px;"></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groupedProducts as $categoryName => $categoryProducts)
            <!-- Cabecera de Categoría -->
            <tr>
                <th colspan="18" style="background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 11px; text-align: left; height: 26px; vertical-align: middle; border: 1px solid #0f172a;">
                    📁 CATEGORÍA: {{ $categoryName }} &nbsp;({{ count($categoryProducts) }} {{ count($categoryProducts) == 1 ? 'producto' : 'productos' }} &nbsp;|&nbsp; Stock Categoría: {{ number_format($categoryProducts->sum('stock'), 2) }})
                </th>
            </tr>
            <!-- Encabezados de Columnas -->
            <tr style="background-color: #e2e8f0; color: #0f172a; font-weight: bold; text-align: center; height: 24px; vertical-align: middle;">
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 40px;">Descripción</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 18px;">Código/SKU</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 18px;">Cód. Auxiliar</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 22px;">Usos / Aplicación</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 16px;">Marca</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 16px;">Bodega/Almacén</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Unidad</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 22px;">Proveedor</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 14px;">P. Compra</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 14px;">P. Venta</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Stock</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Stock Mín.</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Stock Máx.</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">IVA</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 14px;">Desc. Máx</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Es Regalo</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 20px;">Notas</th>
                <th style="font-weight: bold; border: 1px solid #cbd5e1; width: 12px;">Estado</th>
            </tr>
            <!-- Filas de Productos -->
            @foreach ($categoryProducts as $index => $product)
                <tr style="background-color: {{ $index % 2 == 0 ? '#ffffff' : '#f8fafc' }};">
                    <td style="border: 1px solid #e2e8f0; text-align: left;">{{ $product->description }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center; font-weight: 600;">{{ $product->sku ?: '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ $product->code_aux ?: '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: left;">{{ $product->uses ?: '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ $product->brand ?: '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: left;">{{ $product->warehouse ? $product->warehouse->name : 'Sin almacén' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ $product->unit ? $product->unit->name : 'UND' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: left;">{{ $product->supplier ? $product->supplier->name : 'Sin proveedor' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: right;">${{ number_format((float) $product->purchase_price, 2) }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: right; font-weight: bold; color: #0f172a;">${{ number_format((float) $product->price_sale, 2) }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center; font-weight: bold; {{ (float)$product->stock <= 0 ? 'color: #dc2626;' : 'color: #16a34a;' }}">
                        {{ number_format((float) $product->stock, 2) }}
                    </td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ number_format((float) $product->min_stock, 2) }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ number_format((float) $product->max_stock, 2) }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ $product->is_taxable == 1 ? $product->tax_rate . '%' : '0%' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">{{ $product->max_discount ? '$' . number_format((float)$product->max_discount, 2) : '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center;">
                        @if ($product->is_gift == 1)
                            Sí
                        @elseif ($product->is_gift == 2)
                            No
                        @else
                            N/A
                        @endif
                    </td>
                    <td style="border: 1px solid #e2e8f0; text-align: left;">{{ $product->notes ?: '—' }}</td>
                    <td style="border: 1px solid #e2e8f0; text-align: center; font-weight: 600; {{ $product->state == 1 ? 'color: #16a34a;' : 'color: #dc2626;' }}">
                        {{ $product->state == 1 ? 'Activo' : 'Inactivo' }}
                    </td>
                </tr>
            @endforeach
            <!-- Subtotal de Categoría -->
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="10" style="border: 1px solid #cbd5e1; text-align: right; color: #334155; font-size: 10px;">
                    SUBTOTAL {{ $categoryName }} ({{ count($categoryProducts) }} ítems):
                </td>
                <td style="border: 1px solid #cbd5e1; text-align: center; font-weight: bold; color: #0f172a;">
                    {{ number_format($categoryProducts->sum('stock'), 2) }}
                </td>
                <td colspan="7" style="border: 1px solid #cbd5e1;"></td>
            </tr>
            <!-- Fila separadora en blanco -->
            <tr>
                <td colspan="18" style="height: 14px;"></td>
            </tr>
        @endforeach
        <!-- Gran Total -->
        <tr style="background-color: #0f172a; color: #ffffff; font-weight: bold; height: 28px;">
            <td colspan="10" style="border: 1px solid #0f172a; text-align: right; font-size: 11px; vertical-align: middle; color: #ffffff;">
                TOTAL GENERAL ({{ $totalProducts }} PRODUCTOS):
            </td>
            <td style="border: 1px solid #0f172a; text-align: center; font-size: 11px; vertical-align: middle; color: #ffffff;">
                {{ number_format($totalStock, 2) }}
            </td>
            <td colspan="7" style="border: 1px solid #0f172a;"></td>
        </tr>
    </tbody>
</table>

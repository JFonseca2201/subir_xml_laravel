<table>
    <thead>
        <tr>
            <th width="60">Descripción</th>
            <th width="40">Código/SKU</th>
            <th width="40">Imagen</th>
            <th width="20">Código Auxiliar</th>
            <th width="20">Usos</th>
            <th width="20">Categoría</th>
            <th width="20">Bodega</th>
            <th width="20">Unidad</th>
            <th width="20">Proveedor</th>
            <th width="20">Precio</th>
            <th width="20">Precio Venta</th>
            <th width="20">Precio Compra</th>
            <th width="20">Tasa Impuesto</th>
            <th width="20">Descuento Máximo</th>
            <th width="20">Porcentaje Descuento</th>
            <th width="20">Marca</th>
            <th width="20">Stock</th>
            <th width="20">Tipo Ítem</th>
            <th width="20">Stock Mínimo</th>
            <th width="20">Stock Máximo</th>
            <th width="20">Gravable IVA</th>
            <th width="20">Es Regalo</th>
            <th width="20">Notas</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($list_products as $product)
            <tr>
                <td>{{ $product->description }}</td>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->imagen }}</td>
                <td>{{ $product->code_aux }}</td>
                <td>{{ $product->uses }}</td>
                <td>{{ $product->categorie ? $product->categorie->title : 'Sin categoría' }}</td>
                <td>{{ $product->warehouse ? $product->warehouse->name : 'Sin almacén' }}</td>
                <td>{{ $product->unit ? $product->unit->name : 'Sin unidad' }}</td>
                <td>{{ $product->supplier ? $product->supplier->name : 'Sin proveedor' }}</td>
                <td>{{ $product->price }}</td>
                <td>{{ $product->price_sale }}</td>
                <td>{{ $product->purchase_price }}</td>
                <td>{{ $product->tax_rate }}%</td>
                <td>{{ $product->max_discount }}</td>
                <td>{{ $product->discount_percentage }}%</td>
                <td>{{ $product->brand }}</td>
                <td>{{ $product->stock }}</td>
                <td>{{ $product->item_type == 1 ? 'Producto' : 'Servicio' }}</td>
                <td>{{ $product->min_stock }}</td>
                <td>{{ $product->max_stock }}</td>
                <td>{{ $product->is_taxable == 1 ? 'Sujeto a IVA' : 'Exento de IVA' }}</td>
                @if ($product->is_gift == 1)
                    <td style="background-color: #287fa7; color: white;">Sí</td>
                @elseif ($product->is_gift == 2)
                    <td style="background-color: #b83e2e; color: white;">No</td>
                @else
                    <td style="background-color: #6c757d; color: white;">N/A</td>
                @endif
                <td>{{ $product->notes }}</td>
                <td>{{ $product->state == 1 ? 'Activo' : 'Inactivo' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

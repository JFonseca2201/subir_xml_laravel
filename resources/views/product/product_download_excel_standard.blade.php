<table>
    <thead>
        <tr style="background-color: #0f172a; color: #ffffff; font-weight: bold; height: 26px;">
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 45px;">description</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 20px;">sku</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 20px;">code_aux</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 25px;">uses</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 22px;">categoria</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 20px;">bodega</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">unidad</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 25px;">proveedor</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 15px;">price</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 15px;">price_sale</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 15px;">purchase_price</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">tax_rate</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 15px;">max_discount</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 18px;">discount_percentage</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 18px;">brand</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">stock</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">item_type</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">min_stock</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">max_stock</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">is_taxable</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">is_gift</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 25px;">notes</th>
            <th style="font-weight: bold; background-color: #0f172a; color: #ffffff; border: 1px solid #334155; width: 14px;">state</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($list_products as $product)
            <tr>
                <td style="border: 1px solid #e2e8f0;">{{ $product->description }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->sku }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->code_aux }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->uses }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->categorie ? $product->categorie->title : '' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->warehouse ? $product->warehouse->name : '' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->unit ? $product->unit->name : '' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->supplier ? $product->supplier->name : '' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->price }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->price_sale }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->purchase_price }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->tax_rate }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->max_discount }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->discount_percentage }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->brand }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->stock }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->item_type == 1 ? 'Producto' : 'Servicio' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->min_stock }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->max_stock }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->is_taxable == 1 ? 'Sujeto a IVA' : 'Exento de IVA' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->is_gift == 1 ? 'Sí' : 'No' }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->notes }}</td>
                <td style="border: 1px solid #e2e8f0;">{{ $product->state == 1 ? 'Activo' : 'Inactivo' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

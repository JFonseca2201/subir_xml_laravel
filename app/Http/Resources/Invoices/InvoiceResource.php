<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Invoices\InvoiceItemResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->resource->id,
            'supplier_id'    => $this->resource->supplier_id,           
            'supplier' => $this->resource->supplier
                ? collect([$this->resource->supplier])->map(function ($supplier) {
                    return [
                        'id'         => $supplier->id,
                        'name'       => $supplier->name,
                        'tax_id'     => $supplier->tax_id,
                        'trade_name' => $supplier->trade_name,
                        'address'    => $supplier->address,
                    ];
                })->first()
                : null,
            'invoice_number' => $this->resource->invoice_number,
            'access_key'     => $this->resource->access_key,
            'issue_date' => \Carbon\Carbon::parse($this->resource->issue_date)->format('Y-m-d'),
            'subtotal'       => (float) $this->resource->subtotal,
            'tax'            => (float) $this->resource->tax,
            'total'          => (float) $this->resource->total,            
            'invoices_items' => $this->resource->invoices_items->map(function($invoices_item){
                return InvoiceItemResource::make($invoices_item);
            }),            
        ];
    }
}
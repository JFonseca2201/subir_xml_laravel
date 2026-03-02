<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier
                ? collect([$this->supplier])
                ->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'tax_id' => $supplier->tax_id,
                        'trade_name' => $supplier->trade_name,
                        'address' => $supplier->address,
                    ];
                })
                ->first()
                : null,
            'invoice_number' => $this->invoice_number,
            'access_key' => $this->access_key,
            'issue_date' => \Carbon\Carbon::parse($this->issue_date)->format('Y-m-d'),
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'created_at' => $this->created_at->format('Y/m/d h:i:s'),
            'updated_at' => $this->updated_at->format('Y/m/d h:i:s'),
        ];
    }
}

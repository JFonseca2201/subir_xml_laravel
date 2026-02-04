<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'description' => $this->description,
            'quantity'    => (float) $this->quantity,
            'unit_price'  => (float) $this->unit_price,
            'subtotal'    => (float) $this->subtotal,
            'tax'         => (float) $this->tax,
            'total'       => (float) $this->total,
            'item_type'   => (int) $this->item_type,
        ];
    }
}
<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Invoices\InvoiceResource;

class InvoiceCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => InvoiceResource::collection($this->collection),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'data' => InvoiceResource::collection($this->collection),
        ];
    }
    
}
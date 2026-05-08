<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date,
            'type' => $this->type,
            'type_label' => $this->type_label ?? ($this->type === 0 ? 'Ingreso' : 'Egreso'),
            'account_id' => $this->account_id,
            'account_label' => $this->account_label ?? 'Desconocida',
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label ?? ($this->payment_method === 'cash' ? 'Efectivo' : 'Transferencia'),
            'amount' => (float) $this->amount,
            'work_order_number' => $this->work_order_number,
            'invoice_number' => $this->invoice_number,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

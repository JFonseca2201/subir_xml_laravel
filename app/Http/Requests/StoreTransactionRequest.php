<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'account_id' => 'required|exists:accounts,id',
            'type' => 'required|integer|in:1,2,3',
            'amount' => 'required|numeric|gt:0',
            'description' => 'required|string',
            'income_type' => 'nullable|in:capital,work_order',
            'partner_id' => 'nullable|exists:partners,id',
            'employee_id' => 'nullable|exists:employees,id',
            'work_order_id' => 'nullable|integer|min:1',
            'manual_work_order' => 'nullable|string|max:50',
            'invoice_number' => 'nullable|string|max:50',
        ];

        // Validaciones específicas para transferencias (type = 3)
        if ($this->input('type') == 3) {
            $rules['account_destination_id'] = 'required|exists:accounts,id|different:account_id';
        }

        // Validación para ingresos (type = 1): income_type es requerido
        if ($this->input('type') == 1) {
            $rules['income_type'] = 'required|in:capital,work_order';
            
            // Validaciones adicionales según tipo de ingreso
            if ($this->input('income_type') === 'work_order') {
                $rules['work_order_id'] = 'required_without:manual_work_order|nullable|integer|min:1';
                $rules['manual_work_order'] = 'required_without:work_order_id|nullable|string|max:50';
            }
            
            if ($this->input('income_type') === 'capital') {
                $rules['partner_id'] = 'required|exists:partners,id';
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'El campo cuenta es obligatorio.',
            'account_id.exists' => 'La cuenta seleccionada no es válida.',
            'type.required' => 'El campo tipo de transacción es obligatorio.',
            'type.in' => 'El tipo de transacción debe ser 1 (Ingreso), 2 (Egreso) o 3 (Transferencia).',
            'amount.required' => 'El campo monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un valor numérico.',
            'amount.gt' => 'El monto debe ser mayor a 0.',
            'description.required' => 'El campo descripción es obligatorio.',
            'partner_id.exists' => 'El socio seleccionado no es válido.',
            'employee_id.exists' => 'El empleado seleccionado no es válido.',
            'work_order_id.exists' => 'La orden de trabajo seleccionada no es válida.',
            'account_destination_id.required' => 'El campo cuenta destino es obligatorio para transferencias.',
            'account_destination_id.exists' => 'La cuenta destino seleccionada no es válida.',
            'account_destination_id.different' => 'La cuenta destino debe ser diferente a la cuenta origen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'cuenta',
            'type' => 'tipo de transacción',
            'amount' => 'monto',
            'description' => 'descripción',
            'partner_id' => 'socio',
            'employee_id' => 'empleado',
            'work_order_id' => 'orden de trabajo',
            'invoice_number' => 'número de factura',
            'account_destination_id' => 'cuenta destino',
        ];
    }
}

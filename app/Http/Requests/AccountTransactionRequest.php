<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountTransactionRequest extends FormRequest
{
    /**
     * Determine if user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules that apply to request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'type' => 'required|in:income,expense',
            'category' => 'required|in:contribution,salary_payment,salary_advance,transfer,expense_general',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'reference_id' => 'nullable|integer',
            'reference_type' => 'nullable|string|max:50',
            'transaction_date' => 'required|date',
            'transfer_group_id' => 'nullable|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'La cuenta es obligatoria.',
            'account_id.exists' => 'La cuenta seleccionada no existe.',
            'type.required' => 'El tipo de transacción es obligatorio.',
            'type.in' => 'El tipo debe ser income o expense.',
            'category.required' => 'La categoría es obligatoria.',
            'category.in' => 'La categoría seleccionada no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'transaction_date.required' => 'La fecha de transacción es obligatoria.',
            'transaction_date.date' => 'La fecha de transacción debe ser una fecha válida.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceRecordRequest extends FormRequest
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
            'entry_date' => 'sometimes|date|date_format:Y-m-d|before_or_equal:today',
            'type' => 'required|integer|in:0,1',
            'account_id' => 'sometimes|nullable|integer|exists:accounts,id',
            'payment_method' => 'sometimes|string|in:cash,transfer',
            'amount' => 'sometimes|numeric|min:0.01|max:999999999.99',
            'work_order_number' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'payments' => 'required|array|min:1',
            'payments.*.account_id' => 'required|integer|exists:accounts,id',
            'payments.*.amount' => 'required|numeric|min:0.01|max:999999999.99',
        ];

        // En creación de nuevo registro es OBLIGATORIO subir comprobante/foto
        if ($this->isMethod('post') && !$this->has('_method')) {
            $rules['receipts'] = 'required|array|min:1';
            $rules['receipts.*'] = 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:15360';
        } else {
            $rules['receipts'] = 'nullable|array';
            $rules['receipts.*'] = 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:15360';
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
            'type.in' => 'The type must be either 0 (Income) or 1 (Expense).',
            'account_id.exists' => 'The selected account is invalid.',
            'account_id.integer' => 'The selected account must be an integer.',
            'payment_method.in' => 'The payment method must be either cash or transfer.',
            'amount.min' => 'The amount must be at least 0.01.',
            'amount.max' => 'The amount cannot exceed 999,999,999.99.',
            'entry_date.before_or_equal' => 'The entry date cannot be in the future.',
            'entry_date.date_format' => 'The entry date must be in YYYY-MM-DD format.',
            'payments.required' => 'At least one payment method is required.',
            'payments.min' => 'At least one payment method is required.',
            'payments.*.account_id.required' => 'Account is required for each payment.',
            'payments.*.account_id.integer' => 'Account is required for each payment.',
            'payments.*.account_id.exists' => 'The selected account is invalid.',
            'payments.*.amount.required' => 'Amount is required for each payment.',
            'payments.*.amount.min' => 'Payment amount must be at least 0.01.',
            'payments.*.amount.max' => 'Payment amount cannot exceed 999,999,999.99.',
            'receipts.required' => 'El comprobante o foto de respaldo es obligatorio.',
            'receipts.min' => 'Debe adjuntar al menos una foto o comprobante de respaldo.',
            'receipts.*.file' => 'El archivo del comprobante no es válido.',
            'receipts.*.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'receipts.*.max' => 'El comprobante no puede pesar más de 15MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert amount to float
        if ($this->has('amount')) {
            $this->merge([
                'amount' => (float) str_replace(',', '', $this->amount)
            ]);
        }

        // Set payment_method based on account_id if not provided
        if (!$this->has('payment_method') && $this->has('account_id')) {
            $paymentMethod = match ((int) $this->account_id) {
                1 => 'cash',
                default => 'transfer'
            };
            $this->merge(['payment_method' => $paymentMethod]);
        }
    }
}

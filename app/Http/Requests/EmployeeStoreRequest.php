<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
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
        $employeeId = $this->route('employee');

        return [
            'identification' => [
                'required',
                'string',
                'max:20',
                'unique:employees,identification,' . $employeeId,
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0|max:99999999.99',
            'hired_at' => 'required|date|before_or_equal:today',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identification.required' => 'La identificación es obligatoria',
            'identification.unique' => 'Esta identificación ya está registrada',
            'first_name.required' => 'El nombre es obligatorio',
            'last_name.required' => 'El apellido es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El formato del email no es válido',
            'email.unique' => 'Este email ya está registrado',
            'position.required' => 'El cargo es obligatorio',
            'salary.required' => 'El salario es obligatorio',
            'salary.numeric' => 'El salario debe ser un número',
            'salary.min' => 'El salario debe ser mayor o igual a 0',
            'hired_at.required' => 'La fecha de contratación es obligatoria',
            'hired_at.before_or_equal' => 'La fecha de contratación no puede ser futura',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'identification' => trim($this->identification),
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
            'email' => trim($this->email),
            'phone' => trim($this->phone),
            'position' => trim($this->position),
        ]);
    }
}

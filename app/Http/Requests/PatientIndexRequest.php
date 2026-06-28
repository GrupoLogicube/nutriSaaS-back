<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class PatientIndexRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->cleanNullableString($this->input('q')),
            'estado' => $this->cleanNullableString($this->input('estado')),
            'sexo' => $this->cleanNullableString($this->input('sexo')),
            'per_page' => $this->cleanNumeric($this->input('per_page')),
            'page' => $this->cleanNumeric($this->input('page')),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'in:activo,inactivo,todos'],
            'sexo' => ['nullable', 'string', 'max:50'],
            'with_inactive' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

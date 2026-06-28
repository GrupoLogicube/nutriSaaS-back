<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->cleanString($this->input('nombre')),
            'apellido' => $this->cleanString($this->input('apellido')),
            'cedula' => $this->cleanNullableString($this->input('cedula')),
            'sexo' => $this->cleanNullableString($this->input('sexo')),
            'ocupacion' => $this->cleanNullableString($this->input('ocupacion')),
            'tipoConsulta' => $this->cleanNullableString($this->input('tipoConsulta')),
            'edad' => $this->cleanNumeric($this->input('edad')),
            'peso' => $this->cleanNumeric($this->input('peso')),
            'altura' => $this->cleanNumeric($this->input('altura')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:50'],
            'sexo' => ['nullable', 'string', 'max:50'],
            'edad' => ['nullable', 'integer', 'min:0', 'max:130'],
            'peso' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'altura' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'ocupacion' => ['nullable', 'string', 'max:255'],
            'tipoConsulta' => ['nullable', 'string', 'max:255'],
        ];
    }
}

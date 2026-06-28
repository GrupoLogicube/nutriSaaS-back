<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNutricionistaRequest extends FormRequest
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
            'usuario' => $this->cleanString($this->input('usuario')),
            'email' => $this->cleanEmail($this->input('email')),
        ]);
    }

    public function rules(): array
    {
        $nutricionistaId = $this->route('id');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:255', Rule::unique('users', 'usuario')->ignore($nutricionistaId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($nutricionistaId)],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }
}

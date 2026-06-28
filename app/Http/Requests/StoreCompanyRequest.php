<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
            'admin_nombre' => $this->cleanString($this->input('admin_nombre')),
            'admin_apellido' => $this->cleanString($this->input('admin_apellido')),
            'admin_usuario' => $this->cleanString($this->input('admin_usuario')),
            'admin_email' => $this->cleanEmail($this->input('admin_email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'admin_nombre' => ['required', 'string', 'max:255'],
            'admin_apellido' => ['required', 'string', 'max:255'],
            'admin_usuario' => ['required', 'string', 'max:255', Rule::unique('users', 'usuario')],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }
}

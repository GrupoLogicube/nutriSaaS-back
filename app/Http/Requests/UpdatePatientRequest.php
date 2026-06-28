<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombreCompleto = $this->cleanString($this->input('nombre_completo'));
        $nombre = $this->has('nombre') ? $this->cleanString($this->input('nombre')) : null;
        $apellido = $this->has('apellido') ? $this->cleanString($this->input('apellido')) : null;
        $derivedFromFullName = false;

        if ((! is_string($nombre) || $nombre === '') && is_string($nombreCompleto) && $nombreCompleto !== '') {
            $parts = preg_split('/\s+/', trim($nombreCompleto)) ?: [];
            $nombre = array_shift($parts) ?: $nombreCompleto;
            $apellido = trim(implode(' ', $parts));
            $derivedFromFullName = true;
        }

        $data = [];

        if ($this->has('nombre_completo')) {
            $data['nombre_completo'] = $nombreCompleto;
        }

        if ($this->has('nombre') || $derivedFromFullName) {
            $data['nombre'] = $nombre;
        }

        if ($this->has('apellido') || $derivedFromFullName) {
            $data['apellido'] = is_string($apellido) && $apellido !== '' ? $apellido : 'Sin apellido';
        }

        foreach (['email', 'telefono', 'cedula', 'ocupacion', 'tipoConsulta', 'estado'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->cleanNullableString($this->input($field));
            }
        }

        if ($this->has('sexo') || $this->has('sexo_biologico')) {
            $data['sexo'] = $this->cleanNullableString($this->input('sexo') ?? $this->input('sexo_biologico'));
        }

        if ($this->has('fecha_nacimiento')) {
            $data['fecha_nacimiento'] = $this->input('fecha_nacimiento') === '' ? null : $this->input('fecha_nacimiento');
        }

        foreach (['edad', 'peso', 'altura'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->cleanNumeric($this->input($field));
            }
        }

        if ($this->has('perfil_datos')) {
            $data['perfil_datos'] = $this->input('perfil_datos');
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'nombre_completo' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'cedula' => ['nullable', 'string', 'max:50'],
            'sexo' => ['nullable', 'string', 'max:50'],
            'edad' => ['nullable', 'integer', 'min:0', 'max:130'],
            'peso' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'altura' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'ocupacion' => ['nullable', 'string', 'max:255'],
            'tipoConsulta' => ['nullable', 'string', 'max:255'],
            'perfil_datos' => ['nullable', 'array'],
            'estado' => ['nullable', 'string', 'in:activo,inactivo'],
        ];
    }
}

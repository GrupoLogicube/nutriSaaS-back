<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class StorePatientMetricRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bristolScale = $this->input('bristol_scale', $this->input('escala_bristol'));

        if (is_string($bristolScale) && preg_match('/([1-7])/', $bristolScale, $matches)) {
            $bristolScale = $matches[1];
        }

        $this->merge([
            'measured_at' => $this->cleanNullableString($this->input('measured_at', $this->input('fecha'))),
            'weight_kg' => $this->cleanNumeric($this->input('weight_kg', $this->input('peso'))),
            'height_cm' => $this->cleanNumeric($this->input('height_cm', $this->input('altura'))),
            'allergies' => $this->cleanNullableString($this->input('allergies', $this->input('alergias'))),
            'activity_level' => $this->cleanNullableString($this->input('activity_level', $this->input('nivel_actividad'))),
            'bristol_scale' => $this->cleanNumeric($bristolScale),
            'digestive_quality' => $this->cleanNullableString($this->input('digestive_quality', $this->input('calidad_digestiva'))),
            'notes' => $this->cleanNullableString($this->input('notes', $this->input('notas'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['nullable', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'height_cm' => ['required', 'numeric', 'min:30', 'max:300'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'activity_level' => ['nullable', 'string', 'max:100'],
            'bristol_scale' => ['nullable', 'integer', 'min:1', 'max:7'],
            'digestive_quality' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

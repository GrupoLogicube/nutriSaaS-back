<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use App\Models\Paciente;
use Illuminate\Http\Request;

class DietaController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Dieta::query()->latest()->get()]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'objetivos' => ['nullable', 'string'],
            'preferencias' => ['nullable', 'string'],
            'modo' => ['nullable', 'string', 'max:50'],
        ]);

        $paciente = ! empty($validated['paciente_id']) ? Paciente::find($validated['paciente_id']) : null;
        $calorias = match ($validated['modo'] ?? 'smart') {
            'lite' => 1800,
            'advanced' => 2350,
            default => 2100,
        };

        return response()->json([
            'data' => [
                'patientName' => $paciente?->nombre_completo ?? 'Paciente seleccionado',
                'macros' => [
                    'calories' => $calorias,
                    'protein' => 145,
                    'carbs' => 220,
                    'fat' => 70,
                ],
                'days' => $this->sampleDietDays(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'calorias_objetivo' => ['nullable', 'integer', 'min:0'],
            'proteina_objetivo' => ['nullable', 'numeric', 'min:0'],
            'carbohidratos_objetivo' => ['nullable', 'numeric', 'min:0'],
            'grasas_objetivo' => ['nullable', 'numeric', 'min:0'],
            'plan' => ['nullable', 'array'],
            'estado' => ['nullable', 'string', 'max:50'],
        ]);

        $dieta = Dieta::create([
            ...$validated,
            'nombre' => $validated['nombre'] ?? 'Plan alimenticio',
            'estado' => $validated['estado'] ?? 'borrador',
        ]);

        return response()->json(['data' => $dieta, 'message' => 'Dieta guardada'], 201);
    }

    public function destroy(int $id)
    {
        Dieta::findOrFail($id)->delete();

        return response()->json(['message' => 'Dieta eliminada']);
    }

    private function sampleDietDays(): array
    {
        return [
            'lunes' => [
                'desayuno' => 'Avena con fruta y proteina.',
                'colacion1' => 'Yogur natural con semillas.',
                'almuerzo' => 'Pollo, arroz integral y ensalada.',
                'colacion2' => 'Fruta fresca y frutos secos.',
                'cena' => 'Pescado con vegetales.',
            ],
        ];
    }
}

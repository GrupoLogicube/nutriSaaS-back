<?php

namespace App\Http\Controllers;

use App\Models\Rutina;
use Illuminate\Http\Request;

class RutinaController extends Controller
{
    public function index()
    {
        $rutinas = Rutina::query()
            ->latest()
            ->get()
            ->map(fn (Rutina $rutina) => $this->resource($rutina));

        return response()->json(['data' => $rutinas]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable'],
            'objetivo' => ['required', 'string', 'max:100'],
            'nivel' => ['required', 'string', 'max:100'],
            'dias_semana' => ['required', 'integer', 'min:1', 'max:7'],
            'equipamiento' => ['nullable', 'string', 'max:255'],
            'restricciones' => ['nullable', 'string'],
        ]);

        $days = collect(range(1, $validated['dias_semana']))->map(fn (int $day) => [
            'day' => "Dia {$day}",
            'focus' => $this->focusForDay($day),
            'exercises' => [
                ['name' => 'Calentamiento dinamico', 'sets' => '1', 'reps' => '8 min', 'rest' => '0s', 'notes' => 'Movilidad general'],
                ['name' => 'Ejercicio principal', 'sets' => '4', 'reps' => '10-12', 'rest' => '90s', 'notes' => $validated['equipamiento'] ?? 'Adaptar equipamiento'],
                ['name' => 'Trabajo accesorio', 'sets' => '3', 'reps' => '12-15', 'rest' => '60s', 'notes' => $validated['restricciones'] ?? 'Tecnica controlada'],
            ],
        ])->all();

        return response()->json([
            'data' => [
                'name' => 'Rutina personalizada',
                'goal' => $validated['objetivo'],
                'level' => $validated['nivel'],
                'days' => $days,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string', 'max:100'],
            'goal' => ['nullable', 'string', 'max:100'],
            'nivel' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'dias_semana' => ['nullable', 'integer', 'min:1', 'max:7'],
            'equipamiento' => ['nullable', 'string', 'max:255'],
            'restricciones' => ['nullable', 'string'],
            'plan' => ['nullable', 'array'],
            'days' => ['nullable', 'array'],
            'estado' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = $validated['plan'] ?? [
            'name' => $validated['name'] ?? $validated['nombre'] ?? 'Rutina personalizada',
            'goal' => $validated['goal'] ?? $validated['objetivo'] ?? null,
            'level' => $validated['level'] ?? $validated['nivel'] ?? null,
            'days' => $validated['days'] ?? [],
        ];

        $rutina = Rutina::create([
            'paciente_id' => $validated['paciente_id'] ?? null,
            'nombre' => $validated['nombre'] ?? $validated['name'] ?? $plan['name'] ?? 'Rutina personalizada',
            'objetivo' => $validated['objetivo'] ?? $validated['goal'] ?? $plan['goal'] ?? null,
            'nivel' => $validated['nivel'] ?? $validated['level'] ?? $plan['level'] ?? null,
            'dias_semana' => $validated['dias_semana'] ?? count($plan['days'] ?? []),
            'equipamiento' => $validated['equipamiento'] ?? null,
            'restricciones' => $validated['restricciones'] ?? null,
            'plan' => $plan,
            'estado' => $validated['estado'] ?? 'activa',
        ]);

        return response()->json(['data' => $this->resource($rutina), 'message' => 'Rutina guardada'], 201);
    }

    private function focusForDay(int $day): string
    {
        return ['Fuerza tren superior', 'Fuerza tren inferior', 'Core y movilidad', 'Full body'][($day - 1) % 4];
    }

    private function resource(Rutina $rutina): array
    {
        $plan = $rutina->plan ?? [];

        return [
            'id' => $rutina->id,
            'paciente_id' => $rutina->paciente_id,
            'nombre' => $rutina->nombre,
            'name' => $plan['name'] ?? $rutina->nombre,
            'objetivo' => $rutina->objetivo,
            'goal' => $plan['goal'] ?? $rutina->objetivo,
            'nivel' => $rutina->nivel,
            'level' => $plan['level'] ?? $rutina->nivel,
            'dias_semana' => $rutina->dias_semana,
            'days' => $plan['days'] ?? [],
            'equipamiento' => $rutina->equipamiento,
            'restricciones' => $rutina->restricciones,
            'plan' => $plan,
            'estado' => $rutina->estado,
            'created_at' => optional($rutina->created_at)->toISOString(),
            'updated_at' => optional($rutina->updated_at)->toISOString(),
        ];
    }
}

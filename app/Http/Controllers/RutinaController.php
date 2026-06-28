<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RutinaController extends Controller
{
    public function index()
    {
        return response()->json(['data' => []]);
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
        return response()->json(['data' => $request->all(), 'message' => 'Rutina recibida'], 201);
    }

    private function focusForDay(int $day): string
    {
        return ['Fuerza tren superior', 'Fuerza tren inferior', 'Core y movilidad', 'Full body'][($day - 1) % 4];
    }
}

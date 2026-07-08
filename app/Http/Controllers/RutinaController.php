<?php

namespace App\Http\Controllers;

use App\Models\Rutina;
use App\Models\Paciente;
use App\Services\WorkoutAiGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    public function generate(Request $request, WorkoutAiGenerator $aiGenerator)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'objetivo' => ['required', 'string', 'max:100'],
            'nivel' => ['required', 'string', 'max:100'],
            'dias_semana' => ['required', 'integer', 'min:1', 'max:7'],
            'equipamiento' => ['nullable', 'string', 'max:255'],
            'restricciones' => ['nullable', 'string'],
            'modo' => ['nullable', 'string', 'in:lite,smart,advanced'],
            'clinical_analysis' => ['nullable', 'boolean'],
            'analysis_depth' => ['nullable', 'string', 'in:basic,comprehensive,detailed'],
        ]);

        $paciente = ! empty($validated['paciente_id'])
            ? Paciente::find($validated['paciente_id'])
            : null;

        return response()->json([
            'data' => $aiGenerator->generate($paciente, $validated) ?? $this->fallbackRoutine($validated),
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

    public function update(Request $request, int $id)
    {
        $rutina = Rutina::findOrFail($id);

        $validated = $request->validate([
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
            'name' => $validated['name'] ?? $validated['nombre'] ?? $rutina->nombre,
            'goal' => $validated['goal'] ?? $validated['objetivo'] ?? $rutina->objetivo,
            'level' => $validated['level'] ?? $validated['nivel'] ?? $rutina->nivel,
            'days' => $validated['days'] ?? ($rutina->plan['days'] ?? []),
        ];

        $rutina->fill([
            'nombre' => $validated['nombre'] ?? $validated['name'] ?? $plan['name'] ?? $rutina->nombre,
            'objetivo' => $validated['objetivo'] ?? $validated['goal'] ?? $plan['goal'] ?? $rutina->objetivo,
            'nivel' => $validated['nivel'] ?? $validated['level'] ?? $plan['level'] ?? $rutina->nivel,
            'dias_semana' => $validated['dias_semana'] ?? count($plan['days'] ?? []) ?: $rutina->dias_semana,
            'equipamiento' => $validated['equipamiento'] ?? $rutina->equipamiento,
            'restricciones' => $validated['restricciones'] ?? $rutina->restricciones,
            'plan' => $plan,
            'estado' => $validated['estado'] ?? $rutina->estado,
        ]);
        $rutina->save();

        return response()->json(['data' => $this->resource($rutina), 'message' => 'Rutina actualizada']);
    }

    public function destroy(int $id)
    {
        Rutina::findOrFail($id)->delete();

        return response()->json(['message' => 'Rutina eliminada']);
    }

    public function sendToPatient(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'plan' => ['required', 'array'],
        ]);

        $paciente = Paciente::findOrFail($validated['paciente_id']);

        if (blank($paciente->email)) {
            return response()->json([
                'message' => 'El paciente no tiene un correo registrado.',
            ], 422);
        }

        $subject = $validated['nombre'] ?? 'Rutina de entrenamiento NutriSaaS';
        $body = $this->routineEmailBody($paciente, $subject, $validated['plan']);

        Mail::raw($body, fn ($message) => $message
            ->to($paciente->email)
            ->subject($subject));

        return response()->json([
            'message' => 'Rutina enviada al paciente.',
        ]);
    }

    private function fallbackRoutine(array $validated): array
    {
        $goal = $validated['objetivo'];
        $level = $validated['nivel'];
        $equipment = $validated['equipamiento'] ?? 'Sin equipamiento';
        $restrictions = trim((string) ($validated['restricciones'] ?? ''));
        $mode = $validated['modo'] ?? 'smart';

        $days = collect(range(1, $validated['dias_semana']))
            ->map(fn (int $day) => $this->routineDay($day, $goal, $level, $equipment, $restrictions, $mode))
            ->all();

        return [
            'name' => "Rutina {$this->goalLabel($goal)} - {$level}",
            'goal' => $this->goalLabel($goal),
            'level' => $level,
            'days' => $days,
        ];
    }

    private function routineDay(int $day, string $goal, string $level, string $equipment, string $restrictions, string $mode): array
    {
        $templates = $this->routineTemplates($goal);
        $template = $templates[($day - 1) % count($templates)];
        $volume = $this->volumeFor($level, $mode);
        $restrictionNote = $restrictions !== '' ? " Ajustar por restriccion: {$restrictions}." : '';

        return [
            'day' => "Dia {$day}",
            'focus' => $template['focus'],
            'exercises' => array_map(fn (array $exercise) => [
                'name' => $exercise[0],
                'sets' => $exercise[1] ?? $volume['sets'],
                'reps' => $exercise[2] ?? $volume['reps'],
                'rest' => $exercise[3] ?? $volume['rest'],
                'notes' => "Equipo: {$equipment}. {$exercise[4]}{$restrictionNote}",
            ], $template['exercises']),
        ];
    }

    private function routineTemplates(string $goal): array
    {
        return match ($goal) {
            'perder_peso' => [
                ['focus' => 'Full body metabolico', 'exercises' => [
                    ['Sentadilla goblet o peso corporal', null, '12-15', '45s', 'Mantener ritmo continuo.'],
                    ['Remo con banda o mancuerna', null, '12-15', '45s', 'Priorizar rango completo.'],
                    ['Circuito de step ups', '3', '40s', '30s', 'Controlar impacto.'],
                    ['Plancha con toque de hombros', '3', '30-40s', '30s', 'Core activo.'],
                ]],
                ['focus' => 'Cardio fuerza por intervalos', 'exercises' => [
                    ['Peso muerto rumano ligero', null, '12', '60s', 'Tecnica antes que carga.'],
                    ['Press inclinado con mancuernas o flexiones', null, '10-12', '45s', 'Cadencia controlada.'],
                    ['Farmer walk o marcha cargada', '4', '45s', '45s', 'Respiracion nasal si es posible.'],
                    ['Bicicleta o caminata inclinada', '1', '18-25 min', '0s', 'Zona 2 a 3.'],
                ]],
            ],
            'ganar_musculo' => [
                ['focus' => 'Hipertrofia tren superior', 'exercises' => [
                    ['Press de banca o press con mancuernas', null, '8-12', '90s', 'Progresar carga semanalmente.'],
                    ['Remo horizontal', null, '8-12', '90s', 'Pausar un segundo en contraccion.'],
                    ['Press militar', '3', '8-10', '90s', 'Evitar hiperextension lumbar.'],
                    ['Curl y extension de triceps', '3', '10-15', '60s', 'Trabajo accesorio.'],
                ]],
                ['focus' => 'Hipertrofia tren inferior', 'exercises' => [
                    ['Sentadilla o prensa', null, '8-12', '120s', 'Rango tolerable y estable.'],
                    ['Peso muerto rumano', null, '8-10', '120s', 'Control de cadera.'],
                    ['Zancadas caminando', '3', '10 por pierna', '75s', 'Paso largo.'],
                    ['Elevaciones de gemelo', '4', '12-15', '45s', 'Pausa arriba.'],
                ]],
            ],
            'resistencia' => [
                ['focus' => 'Resistencia aerobica y core', 'exercises' => [
                    ['Bloque aerobico continuo', '1', '30-45 min', '0s', 'Zona 2 estable.'],
                    ['Circuito core antirotacion', '3', '12 por lado', '45s', 'Control respiratorio.'],
                    ['Trabajo de movilidad dinamica', '2', '8 min', '0s', 'Preparar articulaciones.'],
                ]],
                ['focus' => 'Tempo e intervalos', 'exercises' => [
                    ['Intervalos moderados', '6', '2 min', '90s', 'RPE 7/10.'],
                    ['Puente de gluteo', '3', '15', '45s', 'Estabilidad lumbo-pelvica.'],
                    ['Remo o jalon ligero', '3', '15', '45s', 'Resistencia muscular.'],
                ]],
            ],
            'flexibilidad' => [
                ['focus' => 'Movilidad global', 'exercises' => [
                    ['Respiracion diafragmatica', '2', '3 min', '0s', 'Iniciar relajacion.'],
                    ['Movilidad de cadera 90/90', '3', '8 por lado', '30s', 'Sin dolor.'],
                    ['Rotaciones toracicas', '3', '10 por lado', '30s', 'Control lento.'],
                    ['Estiramiento posterior activo', '3', '40s', '20s', 'Mantener tension tolerable.'],
                ]],
                ['focus' => 'Fuerza en rango final', 'exercises' => [
                    ['Sentadilla asistida profunda', '3', '8', '45s', 'Control postural.'],
                    ['Peso muerto a una pierna asistido', '3', '8 por lado', '60s', 'Equilibrio y movilidad.'],
                    ['Wall slides', '3', '12', '30s', 'Escapulas activas.'],
                ]],
            ],
            default => [
                ['focus' => 'Tonificacion full body', 'exercises' => [
                    ['Sentadilla con pausa', null, '10-12', '75s', 'Control y postura.'],
                    ['Press con mancuernas o flexiones', null, '10-12', '75s', 'Cadencia 2-1-2.'],
                    ['Remo unilateral', null, '10-12', '75s', 'Evitar rotacion.'],
                    ['Plancha frontal', '3', '35-45s', '45s', 'Linea corporal neutra.'],
                ]],
                ['focus' => 'Tonificacion tren inferior y core', 'exercises' => [
                    ['Hip thrust o puente de gluteo', null, '12-15', '75s', 'Pausa en extension.'],
                    ['Zancada reversa', '3', '10 por pierna', '60s', 'Rodilla estable.'],
                    ['Peso muerto rumano ligero', '3', '12', '75s', 'Control excentrico.'],
                    ['Dead bug', '3', '10 por lado', '30s', 'Core activo.'],
                ]],
            ],
        };
    }

    private function volumeFor(string $level, string $mode): array
    {
        $sets = match (strtolower($level)) {
            'principiante' => '2-3',
            'avanzado' => '4-5',
            default => '3-4',
        };

        return [
            'sets' => $mode === 'advanced' ? '4' : $sets,
            'reps' => $mode === 'lite' ? '10-12' : '8-15',
            'rest' => $mode === 'advanced' ? '90-120s' : '60-90s',
        ];
    }

    private function goalLabel(string $goal): string
    {
        return match ($goal) {
            'perder_peso' => 'Perdida de peso',
            'ganar_musculo' => 'Ganancia muscular',
            'resistencia' => 'Resistencia',
            'flexibilidad' => 'Flexibilidad',
            default => 'Tonificacion',
        };
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

    private function patientName(Paciente $paciente): string
    {
        return $paciente->nombre_completo
            ?: trim(($paciente->nombre ?? '').' '.($paciente->apellido ?? ''))
            ?: 'Paciente seleccionado';
    }

    private function routineEmailBody(Paciente $paciente, string $subject, array $plan): string
    {
        $lines = [
            "Hola {$this->patientName($paciente)},",
            '',
            "Tu nutricionista te ha enviado: {$subject}.",
            '',
            'Resumen de la rutina:',
            '- Objetivo: '.($plan['goal'] ?? $plan['objetivo'] ?? 'N/D'),
            '- Nivel: '.($plan['level'] ?? $plan['nivel'] ?? 'N/D'),
            '',
        ];

        foreach (($plan['days'] ?? []) as $day) {
            $lines[] = ($day['day'] ?? 'Dia').' - '.($day['focus'] ?? 'Entrenamiento');
            foreach (($day['exercises'] ?? []) as $exercise) {
                $lines[] = '- '.($exercise['name'] ?? 'Ejercicio')
                    .' | Series: '.($exercise['sets'] ?? 'N/D')
                    .' | Reps: '.($exercise['reps'] ?? 'N/D')
                    .' | Descanso: '.($exercise['rest'] ?? 'N/D');
                if (! empty($exercise['notes'])) {
                    $lines[] = '  Nota: '.$exercise['notes'];
                }
            }
            $lines[] = '';
        }

        $lines[] = 'Este mensaje fue generado desde NutriSaaS.';

        return implode("\n", $lines);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use App\Models\Paciente;
use App\Services\DietAiGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DietaController extends Controller
{
    public function index()
    {
        $query = Dieta::query()->latest();

        if (request()->filled('paciente_id')) {
            $query->where('paciente_id', request()->integer('paciente_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function generate(Request $request, DietAiGenerator $aiGenerator)
    {
        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'objetivos' => ['nullable', 'string'],
            'preferencias' => ['nullable', 'string'],
            'modo' => ['nullable', 'string', 'in:lite,smart,advanced'],
            'clinical_analysis' => ['nullable', 'boolean'],
            'analysis_depth' => ['nullable', 'string', 'in:basic,comprehensive,detailed'],
        ]);

        $paciente = Paciente::findOrFail($validated['paciente_id']);
        $modo = $validated['modo'] ?? 'smart';
        $objetivos = $validated['objetivos'] ?? '';
        $profile = $paciente->perfil_datos ?? [];
        $macros = $this->calculateMacros($paciente, $profile, $modo, $objetivos);

        $plan = $aiGenerator->generate($paciente, $validated, $macros);

        if (! $plan) {
            return response()->json([
                'message' => $aiGenerator->lastError() ?: 'No se pudo generar la dieta con IA. Intenta nuevamente.',
            ], 503);
        }

        return response()->json([
            'data' => $plan,
            'meta' => [
                'source' => 'github_models',
                'mode' => $modo,
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

        $subject = $validated['nombre'] ?? 'Plan alimenticio NutriSaaS';
        $body = $this->dietEmailBody($paciente, $subject, $validated['plan']);

        Mail::raw($body, fn ($message) => $message
            ->to($paciente->email)
            ->subject($subject));

        return response()->json([
            'message' => 'Plan alimenticio enviado al paciente.',
        ]);
    }

    private function calculateMacros(Paciente $paciente, array $profile, string $modo, string $objetivos): array
    {
        $peso = max((float) ($paciente->peso ?? $profile['peso'] ?? 70), 30);
        $altura = max((float) ($paciente->altura ?? $profile['altura'] ?? 165), 120);
        $edad = max((int) ($paciente->edad ?? $profile['edad'] ?? 35), 15);
        $sexo = strtolower((string) ($paciente->sexo ?? $profile['sexo'] ?? ''));

        $base = (10 * $peso) + (6.25 * $altura) - (5 * $edad);
        $isMale = str_contains($sexo, 'masculino') || str_contains($sexo, 'hombre') || str_contains($sexo, 'male');
        $base += $isMale ? 5 : -161;

        $activity = $this->activityFactor($profile);
        $calories = $base * $activity;
        $goals = strtolower($objetivos);

        if (str_contains($goals, 'perdida') || str_contains($goals, 'perder') || str_contains($goals, 'grasa')) {
            $calories -= 300;
        } elseif (str_contains($goals, 'musculo') || str_contains($goals, 'aumento') || str_contains($goals, 'ganar')) {
            $calories += 250;
        }

        $calories += match ($modo) {
            'lite' => -100,
            'advanced' => 100,
            default => 0,
        };

        $calories = max(1200, (int) (round($calories / 50) * 50));
        $proteinPercent = $this->macroPercent($profile, ['macroProt', 'proteina', 'protein'], 25);
        $fatPercent = $this->macroPercent($profile, ['macroGrasa', 'grasa', 'fat'], 30);
        $carbPercent = max(20, 100 - $proteinPercent - $fatPercent);

        return [
            'calories' => $calories,
            'protein' => (int) round(($calories * ($proteinPercent / 100)) / 4),
            'carbs' => (int) round(($calories * ($carbPercent / 100)) / 4),
            'fat' => (int) round(($calories * ($fatPercent / 100)) / 9),
        ];
    }

    private function activityFactor(array $profile): float
    {
        $activity = strtolower((string) ($profile['factorActividad'] ?? $profile['nivel_actividad'] ?? $profile['actividad'] ?? 'moderado'));

        return match (true) {
            str_contains($activity, 'sedent') => 1.2,
            str_contains($activity, 'liger') => 1.375,
            str_contains($activity, 'alto'), str_contains($activity, 'intenso') => 1.725,
            str_contains($activity, 'atleta'), str_contains($activity, 'muy') => 1.9,
            default => 1.55,
        };
    }

    private function macroPercent(array $profile, array $keys, int $default): int
    {
        foreach ($keys as $key) {
            if (! empty($profile[$key]) && is_numeric($profile[$key])) {
                return min(60, max(10, (int) $profile[$key]));
            }
        }

        return $default;
    }

    private function patientName(Paciente $paciente): string
    {
        return $paciente->nombre_completo
            ?: trim(($paciente->nombre ?? '').' '.($paciente->apellido ?? ''))
            ?: 'Paciente seleccionado';
    }

    private function sampleDietDays(string $preferences, string $modo, string $objetivos): array
    {
        $note = trim($preferences) !== '' ? " Ajustar por preferencias/alergias: {$preferences}." : '';
        $advancedNote = $modo === 'advanced' ? ' Incluir verduras variadas y control de sodio.' : '';
        $goal = strtolower($objetivos);

        if (str_contains($goal, 'musculo') || str_contains($goal, 'aumento') || str_contains($goal, 'ganar')) {
            return [
                'lunes' => [
                    'desayuno' => 'Tortilla de huevos con avena, banana y mantequilla de mani.',
                    'colacion1' => 'Yogur griego con granola y frutos rojos.',
                    'almuerzo' => 'Arroz integral con pechuga de pollo, aguacate y ensalada.'.$advancedNote,
                    'colacion2' => 'Batido de proteina con fruta.',
                    'cena' => 'Pasta integral con atun o tofu y vegetales.'.$note,
                ],
                'martes' => [
                    'desayuno' => 'Pan integral con huevos revueltos y queso fresco.',
                    'colacion1' => 'Sandwich pequeno de pavo o hummus.',
                    'almuerzo' => 'Carne magra con camote y ensalada de colores.',
                    'colacion2' => 'Leche o bebida vegetal con avena.',
                    'cena' => 'Bowl de quinoa con salmon, legumbres y aceite de oliva.',
                ],
                'miercoles' => [
                    'desayuno' => 'Avena cocida con proteina, canela y frutos secos.',
                    'colacion1' => 'Fruta con nueces.',
                    'almuerzo' => 'Pollo salteado con pasta integral y verduras.',
                    'colacion2' => 'Yogur griego con chia.',
                    'cena' => 'Omelette de vegetales con papa asada.',
                ],
                'jueves' => [
                    'desayuno' => 'Arepa o tortilla de maiz con huevo y aguacate.',
                    'colacion1' => 'Batido de fruta con yogur.',
                    'almuerzo' => 'Lentejas con arroz, ensalada y proteina adicional.',
                    'colacion2' => 'Tostada integral con crema de mani.',
                    'cena' => 'Pescado con arroz y vegetales salteados.',
                ],
                'viernes' => [
                    'desayuno' => 'Panqueques de avena con yogur y fruta.',
                    'colacion1' => 'Queso fresco con fruta.',
                    'almuerzo' => 'Bowl de pollo, garbanzos, arroz y aceite de oliva.',
                    'colacion2' => 'Frutos secos y fruta.',
                    'cena' => 'Carne magra o tofu con camote y ensalada.',
                ],
                'sabado' => [
                    'desayuno' => 'Huevos con tostadas integrales y fruta.',
                    'colacion1' => 'Yogur con granola.',
                    'almuerzo' => 'Fajitas de pollo con tortillas, frijoles y guacamole.',
                    'colacion2' => 'Batido de proteina o alternativa vegetal.',
                    'cena' => 'Risotto integral con pescado y vegetales.',
                ],
                'domingo' => [
                    'desayuno' => 'Avena reposada con leche, banana y semillas.',
                    'colacion1' => 'Sandwich pequeno de atun o hummus.',
                    'almuerzo' => 'Pasta integral con pollo, vegetales y aceite de oliva.',
                    'colacion2' => 'Fruta con frutos secos.',
                    'cena' => 'Tortilla de huevo con arroz y ensalada.',
                ],
            ];
        }

        if (str_contains($goal, 'perdida') || str_contains($goal, 'perder') || str_contains($goal, 'grasa')) {
            return [
                'lunes' => [
                    'desayuno' => 'Yogur natural alto en proteina con frutos rojos y chia.',
                    'colacion1' => 'Manzana con almendras porcionadas.',
                    'almuerzo' => 'Pechuga de pollo con ensalada grande y quinoa medida.'.$advancedNote,
                    'colacion2' => 'Palitos de zanahoria con hummus.',
                    'cena' => 'Pescado al horno con vegetales y sopa ligera.'.$note,
                ],
                'martes' => [
                    'desayuno' => 'Huevos revueltos con espinaca y pan integral pequeno.',
                    'colacion1' => 'Fruta fresca.',
                    'almuerzo' => 'Bowl de legumbres con vegetales y proteina magra.',
                    'colacion2' => 'Yogur sin azucar.',
                    'cena' => 'Crema de verduras con pavo o tofu.',
                ],
                'miercoles' => [
                    'desayuno' => 'Batido de bebida vegetal, proteina y fruta baja en azucar.',
                    'colacion1' => 'Pepino o zanahoria con limon.',
                    'almuerzo' => 'Pescado con camote pequeno y ensalada.',
                    'colacion2' => 'Huevo cocido.',
                    'cena' => 'Ensalada completa con pollo y aguacate medido.',
                ],
                'jueves' => [
                    'desayuno' => 'Tostada integral con aguacate medido y huevo.',
                    'colacion1' => 'Pera pequena.',
                    'almuerzo' => 'Carne magra con vegetales salteados y arroz medido.',
                    'colacion2' => 'Queso fresco bajo en grasa.',
                    'cena' => 'Omelette de claras y vegetales.',
                ],
                'viernes' => [
                    'desayuno' => 'Avena porcionada con canela y yogur.',
                    'colacion1' => 'Frutos secos porcionados.',
                    'almuerzo' => 'Ensalada tibia de pollo, garbanzos y verduras.',
                    'colacion2' => 'Fruta con yogur.',
                    'cena' => 'Sopa de verduras con pescado.',
                ],
                'sabado' => [
                    'desayuno' => 'Omelette con vegetales y tortilla de maiz.',
                    'colacion1' => 'Fruta de temporada.',
                    'almuerzo' => 'Fajitas de pollo en hojas verdes con guacamole medido.',
                    'colacion2' => 'Yogur natural.',
                    'cena' => 'Ensalada de atun o tofu con vegetales.',
                ],
                'domingo' => [
                    'desayuno' => 'Avena reposada porcionada con fruta.',
                    'colacion1' => 'Almendras medidas.',
                    'almuerzo' => 'Proteina magra con ensalada y papa pequena.',
                    'colacion2' => 'Infusion y fruta.',
                    'cena' => 'Cena ligera de vegetales y proteina.',
                ],
            ];
        }

        return [
            'lunes' => [
                'desayuno' => 'Avena con fruta, yogur natural y semillas.',
                'colacion1' => 'Manzana con almendras.',
                'almuerzo' => 'Pollo a la plancha con arroz integral y ensalada mixta.'.$advancedNote,
                'colacion2' => 'Yogur natural sin azucar.',
                'cena' => 'Pescado al horno con vegetales salteados.'.$note,
            ],
            'martes' => [
                'desayuno' => 'Huevos revueltos con vegetales y pan integral.',
                'colacion1' => 'Fruta fresca con nueces.',
                'almuerzo' => 'Carne magra o legumbres con quinoa y ensalada.',
                'colacion2' => 'Queso fresco o alternativa vegetal con fruta.',
                'cena' => 'Crema de verduras con pechuga de pavo o tofu.',
            ],
            'miercoles' => [
                'desayuno' => 'Batido con leche o bebida vegetal, fruta y proteina.',
                'colacion1' => 'Palitos de zanahoria con hummus.',
                'almuerzo' => 'Pescado con camote y vegetales al vapor.',
                'colacion2' => 'Yogur griego con chia.',
                'cena' => 'Tortilla de huevo con ensalada verde.',
            ],
            'jueves' => [
                'desayuno' => 'Tostadas integrales con aguacate y queso cottage.',
                'colacion1' => 'Pera con semillas.',
                'almuerzo' => 'Pechuga de pollo con pasta integral y ensalada.',
                'colacion2' => 'Batido pequeno de fruta.',
                'cena' => 'Salteado de verduras con proteina magra.',
            ],
            'viernes' => [
                'desayuno' => 'Panqueques de avena con fruta.',
                'colacion1' => 'Frutos secos porcionados.',
                'almuerzo' => 'Bowl de arroz integral, legumbres, vegetales y aceite de oliva.',
                'colacion2' => 'Huevo cocido o alternativa vegetal.',
                'cena' => 'Sopa de verduras con pescado o pollo.',
            ],
            'sabado' => [
                'desayuno' => 'Omelette con vegetales y tortilla de maiz.',
                'colacion1' => 'Fruta de temporada.',
                'almuerzo' => 'Fajitas de pollo con vegetales y guacamole.',
                'colacion2' => 'Yogur natural con semillas.',
                'cena' => 'Ensalada completa con proteina y carbohidrato complejo.',
            ],
            'domingo' => [
                'desayuno' => 'Avena reposada con fruta y canela.',
                'colacion1' => 'Almendras o nueces.',
                'almuerzo' => 'Proteina magra con papa o arroz y ensalada.',
                'colacion2' => 'Fruta con yogur.',
                'cena' => 'Cena ligera con vegetales y proteina.',
            ],
        ];
    }

    private function dietEmailBody(Paciente $paciente, string $subject, array $plan): string
    {
        $lines = [
            "Hola {$this->patientName($paciente)},",
            '',
            "Tu nutricionista te ha enviado: {$subject}.",
            '',
        ];

        $macros = $plan['macros'] ?? [];
        if (! empty($macros)) {
            $lines[] = 'Resumen nutricional diario:';
            $lines[] = '- Calorias: '.($macros['calories'] ?? 'N/D');
            $lines[] = '- Proteina: '.($macros['protein'] ?? 'N/D').' g';
            $lines[] = '- Carbohidratos: '.($macros['carbs'] ?? 'N/D').' g';
            $lines[] = '- Grasas: '.($macros['fat'] ?? 'N/D').' g';
            $lines[] = '';
        }

        foreach (($plan['days'] ?? []) as $day => $meals) {
            $lines[] = strtoupper((string) $day);
            foreach ((array) $meals as $meal => $description) {
                $lines[] = ucfirst((string) $meal).': '.$description;
            }
            $lines[] = '';
        }

        $lines[] = 'Este mensaje fue generado desde NutriSaaS.';

        return implode("\n", $lines);
    }
}

<?php

namespace App\Services;

use App\Models\Paciente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DietAiGenerator
{
    private ?string $lastError = null;

    public function generate(Paciente $paciente, array $validated, array $macros): ?array
    {
        $this->lastError = null;
        $token = config('services.github_models.token');

        if (blank($token)) {
            $this->lastError = 'No está configurado GITHUB_MODELS_TOKEN para generar dietas con IA.';
            return null;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => json_encode($this->payload($paciente, $validated, $macros), JSON_UNESCAPED_UNICODE),
            ],
        ];

        $response = null;

        foreach ($this->modelCandidates() as $model) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(45)
                    ->post(config('services.github_models.endpoint'), [
                        'model' => $model,
                        'messages' => $messages,
                        'response_format' => ['type' => 'json_object'],
                    ]);
            } catch (Throwable $exception) {
                $this->lastError = 'No se pudo conectar con GitHub Models.';
                Log::warning('Diet AI generation request failed.', [
                    'model' => $model,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }

            if ($response->successful()) {
                break;
            }

            if (! $this->isUnavailableModelResponse($response)) {
                break;
            }

            Log::warning('Diet AI generation model unavailable, trying fallback.', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        if (! $response->successful() && ((int) $response->status() === 429 || $this->isUnavailableModelResponse($response))) {
            $this->lastError = $this->errorMessageForResponse($response);
            Log::warning('Diet AI generation returned a handled unsuccessful response.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->lastError = 'GitHub Models no respondió correctamente.';
            Log::warning('Diet AI generation returned an unsuccessful response.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            $this->lastError = 'GitHub Models devolvió una respuesta vacía.';
            return null;
        }

        $plan = json_decode($this->stripMarkdownFence($content), true);

        if (! is_array($plan) || ! $this->hasValidShape($plan)) {
            $this->lastError = 'GitHub Models devolvió una dieta incompleta o demasiado genérica.';
            Log::warning('Diet AI generation returned an invalid plan shape.', [
                'content' => $content,
            ]);

            return null;
        }

        $plan['macros'] = $macros;
        $plan['patientName'] = $plan['patientName'] ?? $this->patientName($paciente);

        return $plan;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres un asistente nutricional para un SaaS clinico. Genera planes alimenticios en español para revision de un nutricionista.
Devuelve exclusivamente JSON valido, sin markdown ni explicaciones externas.
La forma exacta debe ser:
{
  "patientName": "string",
  "macros": {"calories": 0, "protein": 0, "carbs": 0, "fat": 0},
  "pes": null | {"diagnosis": "string", "justification": "string", "monitoring": "string"},
  "days": {
    "lunes": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "martes": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "miercoles": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "jueves": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "viernes": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "sabado": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"},
    "domingo": {"desayuno": "string", "colacion1": "string", "almuerzo": "string", "colacion2": "string", "cena": "string"}
  }
}
Respeta alergias, preferencias, objetivos, cultura alimentaria, modo, profundidad de analisis y los macros indicados. No diagnostiques enfermedades nuevas.
Los menus deben cambiar de forma evidente segun el objetivo: perdida de grasa, ganancia muscular, rendimiento, mantenimiento u otro. No repitas la misma estructura diaria con pequenas variaciones. Ajusta porciones, densidad calorica, timing de carbohidratos, proteinas y grasas segun el objetivo y el modo seleccionado.
Modo lite: menu directo, practico, con preparaciones simples y menos detalle clinico.
Modo smart: menu equilibrado, variado, con preparaciones realistas y distribucion diaria consistente.
Modo advanced: menu clinico con mayor especificidad, porciones, timing de carbohidratos/proteinas, justificacion PES si se solicita y ajustes finos segun historial.
PROMPT;
    }

    private function payload(Paciente $paciente, array $validated, array $macros): array
    {
        return [
            'paciente' => [
                'nombre' => $this->patientName($paciente),
                'sexo' => $paciente->sexo,
                'edad' => $paciente->edad,
                'peso' => $paciente->peso,
                'altura' => $paciente->altura,
                'perfil_datos' => $paciente->perfil_datos ?? [],
            ],
            'solicitud' => [
                'objetivos' => $validated['objetivos'] ?? '',
                'preferencias' => $validated['preferencias'] ?? '',
                'modo' => $validated['modo'] ?? 'smart',
                'clinical_analysis' => (bool) ($validated['clinical_analysis'] ?? false),
                'analysis_depth' => $validated['analysis_depth'] ?? 'comprehensive',
                'modo_instrucciones' => $this->modeInstructions($validated['modo'] ?? 'smart'),
            ],
            'macros' => $macros,
        ];
    }

    private function stripMarkdownFence(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        return trim((string) $content);
    }

    private function hasValidShape(array $plan): bool
    {
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $meals = ['desayuno', 'colacion1', 'almuerzo', 'colacion2', 'cena'];
        $daySignatures = [];

        foreach ($days as $day) {
            if (! isset($plan['days'][$day]) || ! is_array($plan['days'][$day])) {
                return false;
            }

            foreach ($meals as $meal) {
                if (blank($plan['days'][$day][$meal] ?? null)) {
                    return false;
                }
            }

            $daySignatures[] = md5(json_encode($plan['days'][$day], JSON_UNESCAPED_UNICODE));
        }

        return count(array_unique($daySignatures)) >= 4;
    }

    private function modelCandidates(): array
    {
        $configured = config('services.github_models.model', 'openai/gpt-4.1-mini');

        return array_values(array_unique(array_filter([
            is_string($configured) ? trim($configured) : null,
            'openai/gpt-4.1-mini',
            'openai/gpt-4.1',
            'openai/gpt-4o-mini',
        ])));
    }

    private function isUnavailableModelResponse($response): bool
    {
        if ((int) $response->status() !== 400) {
            return false;
        }

        return data_get($response->json(), 'error.code') === 'unavailable_model';
    }

    private function errorMessageForResponse($response): string
    {
        if ((int) $response->status() === 429) {
            return 'GitHub Models alcanzo el limite de solicitudes. Espera unos minutos e intenta nuevamente.';
        }

        if ($this->isUnavailableModelResponse($response)) {
            return 'El modelo configurado en GitHub Models no esta disponible. Revisa GITHUB_MODELS_MODEL.';
        }

        return 'GitHub Models no respondio correctamente.';
    }

    private function modeInstructions(string $mode): string
    {
        return match ($mode) {
            'lite' => 'Plan simple, directo, con comidas faciles de preparar y baja complejidad.',
            'advanced' => 'Plan clinico avanzado, con mayor detalle de porciones, timing nutricional y razonamiento segun datos del paciente.',
            default => 'Plan inteligente balanceado, variado y sostenible, con recetas realistas.',
        };
    }

    private function patientName(Paciente $paciente): string
    {
        return $paciente->nombre_completo
            ?: trim(($paciente->nombre ?? '').' '.($paciente->apellido ?? ''))
            ?: 'Paciente seleccionado';
    }
}

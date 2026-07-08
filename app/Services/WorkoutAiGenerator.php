<?php

namespace App\Services;

use App\Models\Paciente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkoutAiGenerator
{
    public function generate(?Paciente $paciente, array $validated): ?array
    {
        $token = config('services.github_models.token');

        if (blank($token)) {
            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => json_encode($this->payload($paciente, $validated), JSON_UNESCAPED_UNICODE)],
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
                Log::warning('Workout AI generation request failed.', [
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

            Log::warning('Workout AI generation model unavailable, trying fallback.', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        if (! $response->successful()) {
            return null;
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $routine = json_decode($this->stripMarkdownFence($content), true);

        if (! is_array($routine) || ! $this->hasValidShape($routine, (int) $validated['dias_semana'])) {
            return null;
        }

        return $routine;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres un preparador fisico clinico para un SaaS de nutricion. Genera rutinas en espanol para revision profesional.
Devuelve exclusivamente JSON valido, sin markdown ni texto externo.
La forma exacta debe ser:
{
  "name": "string",
  "goal": "string",
  "level": "string",
  "days": [
    {
      "day": "string",
      "focus": "string",
      "exercises": [
        {"name": "string", "sets": "string", "reps": "string", "rest": "string", "notes": "string"}
      ]
    }
  ]
}
La rutina debe cambiar de forma evidente segun objetivo, nivel, dias por semana, equipamiento, restricciones, modo y profundidad de analisis. Respeta lesiones y restricciones. No incluyas ejercicios incompatibles con el equipamiento indicado.
PROMPT;
    }

    private function payload(?Paciente $paciente, array $validated): array
    {
        return [
            'paciente' => $paciente ? [
                'nombre' => $paciente->nombre_completo ?: trim(($paciente->nombre ?? '').' '.($paciente->apellido ?? '')),
                'sexo' => $paciente->sexo,
                'edad' => $paciente->edad,
                'peso' => $paciente->peso,
                'altura' => $paciente->altura,
                'perfil_datos' => $paciente->perfil_datos ?? [],
            ] : null,
            'solicitud' => [
                'objetivo' => $validated['objetivo'],
                'nivel' => $validated['nivel'],
                'dias_semana' => (int) $validated['dias_semana'],
                'equipamiento' => $validated['equipamiento'] ?? 'Sin equipamiento',
                'restricciones' => $validated['restricciones'] ?? '',
                'modo' => $validated['modo'] ?? 'smart',
                'clinical_analysis' => (bool) ($validated['clinical_analysis'] ?? false),
                'analysis_depth' => $validated['analysis_depth'] ?? 'comprehensive',
            ],
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

    private function hasValidShape(array $routine, int $expectedDays): bool
    {
        if (blank($routine['name'] ?? null) || blank($routine['goal'] ?? null) || blank($routine['level'] ?? null)) {
            return false;
        }

        if (! isset($routine['days']) || ! is_array($routine['days']) || count($routine['days']) !== $expectedDays) {
            return false;
        }

        foreach ($routine['days'] as $day) {
            if (blank($day['day'] ?? null) || blank($day['focus'] ?? null) || empty($day['exercises']) || ! is_array($day['exercises'])) {
                return false;
            }

            foreach ($day['exercises'] as $exercise) {
                foreach (['name', 'sets', 'reps', 'rest', 'notes'] as $field) {
                    if (blank($exercise[$field] ?? null)) {
                        return false;
                    }
                }
            }
        }

        return true;
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
}

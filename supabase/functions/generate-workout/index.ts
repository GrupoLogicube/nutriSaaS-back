import { handleGeneration } from '../_shared/ai_generation.ts';

Deno.serve((req) =>
  handleGeneration(req, {
    kind: 'workout',
    generationType: 'workout_routine',
    targetTable: 'workout_routines',
    titleFallback: 'Rutina generada con IA',
    systemPrompt: `
Eres un entrenador y nutricionista deportivo senior. Genera una rutina segura, progresiva y personalizada.
No indiques ejercicios contraindicados si hay lesiones o riesgos; sugiere validacion profesional cuando aplique.
Devuelve JSON valido con esta forma:
{
  "title": string,
  "summary": string,
  "assumptions": string[],
  "weekly_structure": [
    {
      "day": string,
      "focus": string,
      "warmup": string[],
      "exercises": [
        {
          "name": string,
          "sets": string,
          "reps": string,
          "rest": string,
          "rir": string | null,
          "notes": string | null
        }
      ],
      "cooldown": string[]
    }
  ],
  "progression": string[],
  "warnings": string[]
}
`.trim(),
  })
);

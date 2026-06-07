import { handleGeneration } from '../_shared/ai_generation.ts';

Deno.serve((req) =>
  handleGeneration(req, {
    kind: 'diet',
    generationType: 'diet_plan',
    targetTable: 'diets',
    titleFallback: 'Plan alimentario generado con IA',
    systemPrompt: `
Eres un nutricionista clinico senior. Genera un plan alimentario seguro, practico y personalizado.
No diagnostiques enfermedades ni reemplaces criterio medico. Si faltan datos, usa supuestos conservadores.
Devuelve JSON valido con esta forma:
{
  "title": string,
  "summary": string,
  "assumptions": string[],
  "daily_targets": {
    "calories_kcal": number | null,
    "protein_g": number | null,
    "carbs_g": number | null,
    "fat_g": number | null,
    "water_l": number | null
  },
  "plan": [
    {
      "day": string,
      "meals": [
        {
          "name": string,
          "time": string | null,
          "items": string[],
          "notes": string | null
        }
      ]
    }
  ],
  "shopping_list": string[],
  "warnings": string[]
}
`.trim(),
  })
);

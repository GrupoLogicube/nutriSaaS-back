<?php

namespace Tests\Unit;

use App\Models\Paciente;
use App\Services\DietAiGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DietAiGeneratorTest extends TestCase
{
    public function test_it_returns_null_without_token(): void
    {
        config(['services.github_models.token' => null]);

        Http::fake();

        $result = app(DietAiGenerator::class)->generate(
            new Paciente(['nombre' => 'Ana', 'apellido' => 'Lopez']),
            [],
            ['calories' => 1800, 'protein' => 120, 'carbs' => 190, 'fat' => 60],
        );

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_it_generates_a_valid_plan_from_github_models_response(): void
    {
        config([
            'services.github_models.token' => 'test-token',
            'services.github_models.endpoint' => 'https://models.github.ai/inference/chat/completions',
            'services.github_models.model' => 'openai/gpt-5',
        ]);

        Http::fake([
            'models.github.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'patientName' => 'Ana Lopez',
                                'macros' => ['calories' => 1, 'protein' => 1, 'carbs' => 1, 'fat' => 1],
                                'pes' => null,
                                'days' => $this->validDays(),
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $macros = ['calories' => 1800, 'protein' => 120, 'carbs' => 190, 'fat' => 60];
        $result = app(DietAiGenerator::class)->generate(
            new Paciente(['nombre' => 'Ana', 'apellido' => 'Lopez', 'edad' => 30]),
            ['modo' => 'smart'],
            $macros,
        );

        $this->assertSame('Ana Lopez', $result['patientName']);
        $this->assertSame($macros, $result['macros']);
        $this->assertArrayHasKey('domingo', $result['days']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['model'] === 'openai/gpt-5');
    }

    public function test_it_rejects_repeated_generic_days_from_github_models_response(): void
    {
        config([
            'services.github_models.token' => 'test-token',
            'services.github_models.endpoint' => 'https://models.github.ai/inference/chat/completions',
            'services.github_models.model' => 'openai/gpt-5',
        ]);

        $genericDay = [
            'desayuno' => 'Avena con fruta.',
            'colacion1' => 'Fruta con nueces.',
            'almuerzo' => 'Pollo con arroz y ensalada.',
            'colacion2' => 'Yogur natural.',
            'cena' => 'Pescado con vegetales.',
        ];

        Http::fake([
            'models.github.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'patientName' => 'Ana Lopez',
                                'macros' => ['calories' => 1, 'protein' => 1, 'carbs' => 1, 'fat' => 1],
                                'pes' => null,
                                'days' => [
                                    'lunes' => $genericDay,
                                    'martes' => $genericDay,
                                    'miercoles' => $genericDay,
                                    'jueves' => $genericDay,
                                    'viernes' => $genericDay,
                                    'sabado' => $genericDay,
                                    'domingo' => $genericDay,
                                ],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $generator = app(DietAiGenerator::class);

        $result = $generator->generate(
            new Paciente(['nombre' => 'Ana', 'apellido' => 'Lopez', 'edad' => 30]),
            ['modo' => 'smart'],
            ['calories' => 1800, 'protein' => 120, 'carbs' => 190, 'fat' => 60],
        );

        $this->assertNull($result);
        $this->assertSame('GitHub Models devolvió una dieta incompleta o demasiado genérica.', $generator->lastError());
    }

    private function validDays(): array
    {
        return [
            'lunes' => [
                'desayuno' => 'Avena con fruta.',
                'colacion1' => 'Fruta con nueces.',
                'almuerzo' => 'Pollo con arroz y ensalada.',
                'colacion2' => 'Yogur natural.',
                'cena' => 'Pescado con vegetales.',
            ],
            'martes' => [
                'desayuno' => 'Huevos con pan integral.',
                'colacion1' => 'Yogur con chia.',
                'almuerzo' => 'Carne magra con camote.',
                'colacion2' => 'Manzana con almendras.',
                'cena' => 'Tortilla con ensalada.',
            ],
            'miercoles' => [
                'desayuno' => 'Batido con fruta y proteina.',
                'colacion1' => 'Palitos de zanahoria con hummus.',
                'almuerzo' => 'Pescado con quinoa.',
                'colacion2' => 'Queso fresco con fruta.',
                'cena' => 'Sopa de verduras con pollo.',
            ],
            'jueves' => [
                'desayuno' => 'Tostada integral con aguacate.',
                'colacion1' => 'Pera con semillas.',
                'almuerzo' => 'Legumbres con arroz integral.',
                'colacion2' => 'Yogur griego.',
                'cena' => 'Tofu con vegetales.',
            ],
            'viernes' => [
                'desayuno' => 'Panqueques de avena.',
                'colacion1' => 'Frutos secos porcionados.',
                'almuerzo' => 'Bowl de pollo y garbanzos.',
                'colacion2' => 'Huevo cocido.',
                'cena' => 'Pescado con ensalada.',
            ],
            'sabado' => [
                'desayuno' => 'Omelette con vegetales.',
                'colacion1' => 'Fruta de temporada.',
                'almuerzo' => 'Fajitas de pollo.',
                'colacion2' => 'Yogur con semillas.',
                'cena' => 'Ensalada completa.',
            ],
            'domingo' => [
                'desayuno' => 'Avena reposada con canela.',
                'colacion1' => 'Nueces porcionadas.',
                'almuerzo' => 'Proteina magra con papa.',
                'colacion2' => 'Fruta con yogur.',
                'cena' => 'Vegetales con proteina.',
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use App\Models\NotaClinica;
use App\Models\Paciente;
use Illuminate\Http\Request;

class SuscripcionController extends Controller
{
    private const PLANS = [
        'starter' => ['name' => 'Starter', 'price' => '$29/mes', 'pacientes' => 50, 'dietas' => 100, 'notas' => 200],
        'pro' => ['name' => 'Pro', 'price' => '$79/mes', 'pacientes' => 0, 'dietas' => 0, 'notas' => 0],
        'enterprise' => ['name' => 'Enterprise', 'price' => '$199/mes', 'pacientes' => 0, 'dietas' => 0, 'notas' => 0],
    ];

    public function show(Request $request)
    {
        return response()->json(['data' => $this->subscription('starter', 'activo')]);
    }

    public function usage()
    {
        $plan = self::PLANS['starter'];

        return response()->json([
            'data' => [
                'pacientes' => ['used' => Paciente::query()->count(), 'max' => $plan['pacientes']],
                'dietas' => ['used' => Dieta::query()->count(), 'max' => $plan['dietas']],
                'notas' => ['used' => NotaClinica::query()->count(), 'max' => $plan['notas']],
            ],
        ]);
    }

    public function changePlan(Request $request)
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:starter,pro,enterprise'],
            'billing' => ['nullable', 'string', 'in:monthly,annual'],
        ]);

        return response()->json([
            'data' => $this->subscription($validated['plan'], 'activo', $validated['billing'] ?? 'monthly'),
            'message' => 'Plan actualizado',
        ]);
    }

    public function cancel()
    {
        return response()->json([
            'data' => $this->subscription('starter', 'cancelado'),
            'message' => 'Suscripcion cancelada',
        ]);
    }

    private function subscription(string $planId, string $estado, string $billing = 'monthly'): array
    {
        $plan = self::PLANS[$planId] ?? self::PLANS['starter'];

        return [
            'plan_id' => $planId,
            'plan' => $planId,
            'plan_nombre' => $plan['name'],
            'plan_name' => $plan['name'],
            'precio' => $plan['price'],
            'price' => $plan['price'],
            'estado' => $estado,
            'status' => $estado,
            'billing' => $billing,
            'proxima_factura' => now()->addMonth()->toDateString(),
            'next_billing' => now()->addMonth()->toDateString(),
            'dias_restantes' => 30,
            'days_left' => 30,
            'progreso_ciclo' => 0,
            'cycle_progress' => 0,
            'historial_pagos' => [],
        ];
    }
}

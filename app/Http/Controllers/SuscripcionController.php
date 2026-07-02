<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use App\Models\NotaClinica;
use App\Models\Paciente;
use App\Models\Suscripcion;
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
        return response()->json(['data' => $this->resource($this->currentSubscription())]);
    }

    public function usage()
    {
        $subscription = $this->currentSubscription();
        $plan = self::PLANS[$subscription->plan] ?? self::PLANS['starter'];

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

        $subscription = $this->currentSubscription();
        $subscription->fill([
            'plan' => $validated['plan'],
            'billing' => $validated['billing'] ?? $subscription->billing ?? 'monthly',
            'estado' => 'activo',
            'proxima_factura' => now()->addMonth()->toDateString(),
        ])->save();

        return response()->json([
            'data' => $this->resource($subscription->refresh()),
            'message' => 'Plan actualizado',
        ]);
    }

    public function cancel()
    {
        $subscription = $this->currentSubscription();
        $subscription->estado = 'cancelado';
        $subscription->save();

        return response()->json([
            'data' => $this->resource($subscription->refresh()),
            'message' => 'Suscripcion cancelada',
        ]);
    }

    private function currentSubscription(): Suscripcion
    {
        return Suscripcion::query()->firstOrCreate(
            ['id' => 1],
            [
                'plan' => 'starter',
                'billing' => 'monthly',
                'estado' => 'activo',
                'proxima_factura' => now()->addMonth()->toDateString(),
                'historial_pagos' => [],
                'metodo_pago' => null,
            ]
        );
    }

    private function resource(Suscripcion $subscription): array
    {
        $planId = $subscription->plan ?: 'starter';
        $plan = self::PLANS[$planId] ?? self::PLANS['starter'];
        $nextBilling = optional($subscription->proxima_factura)->toDateString() ?? now()->addMonth()->toDateString();
        $daysLeft = max(0, now()->startOfDay()->diffInDays($nextBilling, false));

        return [
            'plan_id' => $planId,
            'plan' => $planId,
            'plan_nombre' => $plan['name'],
            'plan_name' => $plan['name'],
            'precio' => $plan['price'],
            'price' => $plan['price'],
            'estado' => $subscription->estado,
            'status' => $subscription->estado,
            'billing' => $subscription->billing,
            'proxima_factura' => $nextBilling,
            'next_billing' => $nextBilling,
            'dias_restantes' => $daysLeft,
            'days_left' => $daysLeft,
            'progreso_ciclo' => min(100, max(0, 100 - (int) round(($daysLeft / 30) * 100))),
            'cycle_progress' => min(100, max(0, 100 - (int) round(($daysLeft / 30) * 100))),
            'historial_pagos' => $subscription->historial_pagos ?? [],
            'billing_history' => $subscription->historial_pagos ?? [],
            'metodo_pago' => $subscription->metodo_pago,
            'payment_method' => $subscription->metodo_pago,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Dieta;
use App\Models\NotaClinica;
use App\Models\Paciente;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $year = now()->year;
        $pacientes = Paciente::query()->get();
        $dietas = Dieta::query()->get();
        $citas = Cita::query()->get();

        $pacientesPorMes = collect(range(1, 12))->map(fn (int $month) => [
            'label' => now()->month($month)->locale('es')->shortMonthName,
            'value' => $pacientes->filter(fn ($p) => optional($p->created_at)->year === $year && optional($p->created_at)->month === $month)->count(),
        ])->values();

        $startOfWeek = now()->startOfWeek();
        $actividadSemanal = collect(range(0, 6))->map(function (int $offset) use ($startOfWeek, $citas) {
            $day = $startOfWeek->copy()->addDays($offset);

            return [
                'label' => ucfirst($day->locale('es')->isoFormat('dd')),
                'value' => $citas->filter(fn ($cita) => optional($cita->fecha_hora)->isSameDay($day))->count(),
            ];
        })->values();

        $planesPorPaciente = $dietas->groupBy('paciente_id')->map->count();
        $topPacientes = $pacientes
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre_completo' => $p->nombre_completo,
                'planCount' => $planesPorPaciente[$p->id] ?? 0,
            ])
            ->sortByDesc('planCount')
            ->take(5)
            ->values();

        return response()->json([
            'data' => [
                'totalPacientes' => $pacientes->count(),
                'planesGenerados' => $dietas->count(),
                'citasRealizadas' => $citas->where('estado', 'finalizada')->count(),
                'tasaRetencion' => $pacientes->count() > 0 ? round(($dietas->count() / $pacientes->count()) * 100) : 0,
                'pacientesPorMes' => $pacientesPorMes,
                'actividadSemanal' => $actividadSemanal,
                'topPacientes' => $topPacientes,
                'moduleUsage' => [
                    'pacientes' => $pacientes->count(),
                    'dietas' => $dietas->count(),
                    'citas' => $citas->count(),
                    'rutinas' => 0,
                    'notas' => NotaClinica::query()->count(),
                ],
            ],
        ]);
    }
}

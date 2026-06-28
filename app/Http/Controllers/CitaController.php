<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    public function index()
    {
        return response()->json(
            Cita::query()->latest('fecha_hora')->get()->map(fn (Cita $cita) => $this->resource($cita))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'paciente_nombre' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'fecha_hora_inicio' => ['required_without:fecha_hora', 'date'],
            'fecha_hora' => ['required_without:fecha_hora_inicio', 'date'],
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:480'],
            'estado' => ['nullable', 'string', 'max:50'],
            'motivo' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ]);

        $cita = Cita::create($this->payload($validated));

        return response()->json(['data' => $this->resource($cita), 'message' => 'Cita creada'], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'paciente_nombre' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'fecha_hora_inicio' => ['nullable', 'date'],
            'fecha_hora' => ['nullable', 'date'],
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:480'],
            'estado' => ['nullable', 'string', 'max:50'],
            'motivo' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ]);

        $cita = Cita::findOrFail($id);
        $cita->fill($this->payload($validated))->save();

        return response()->json(['data' => $this->resource($cita), 'message' => 'Cita actualizada']);
    }

    public function destroy(int $id)
    {
        Cita::findOrFail($id)->delete();

        return response()->json(['message' => 'Cita eliminada']);
    }

    private function payload(array $validated): array
    {
        return [
            ...$validated,
            'fecha_hora' => $validated['fecha_hora_inicio'] ?? $validated['fecha_hora'] ?? null,
            'tipo' => $validated['tipo'] ?? 'Presencial',
            'duracion_minutos' => $validated['duracion_minutos'] ?? 60,
            'estado' => $validated['estado'] ?? 'programada',
        ];
    }

    private function resource(Cita $cita): array
    {
        return [
            'id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'paciente_nombre' => $cita->paciente_nombre,
            'tipo' => $cita->tipo,
            'fecha_hora' => optional($cita->fecha_hora)->toDateTimeString(),
            'fecha_hora_inicio' => optional($cita->fecha_hora)->toDateTimeString(),
            'duracion_minutos' => $cita->duracion_minutos,
            'estado' => $cita->estado,
            'motivo' => $cita->motivo,
            'notas' => $cita->notas,
            'created_at' => optional($cita->created_at)->toISOString(),
            'updated_at' => optional($cita->updated_at)->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientIndexRequest;
use App\Http\Requests\StorePacienteRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Paciente;

class PacienteController extends Controller
{
    public function index(PatientIndexRequest $request)
    {
        $validated = $request->validated();
        $query = Paciente::query()->latest('updated_at');

        if (($validated['estado'] ?? null) === 'todos' || $request->boolean('with_inactive')) {
            $query->withTrashed();
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];

            $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                    ->where('nombre_completo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['estado']) && $validated['estado'] !== 'todos') {
            $query->where('estado', $validated['estado']);
        }

        if (! empty($validated['sexo'])) {
            $query->where('sexo', $validated['sexo']);
        }

        if (! $request->hasAny(['page', 'per_page', 'q', 'estado', 'sexo', 'with_inactive'])) {
            return response()->json($query->get(), 200);
        }

        $pacientes = $query->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $pacientes->items(),
            'meta' => [
                'current_page' => $pacientes->currentPage(),
                'from' => $pacientes->firstItem(),
                'last_page' => $pacientes->lastPage(),
                'per_page' => $pacientes->perPage(),
                'to' => $pacientes->lastItem(),
                'total' => $pacientes->total(),
            ],
        ], 200);
    }

    public function store(StorePacienteRequest $request)
    {
        $validated = $request->validated();

        $paciente = new Paciente();
        $paciente->fill($validated);
        // Auto-generate full name
        $paciente->nombre_completo = $validated['nombre'] . ' ' . $validated['apellido'];
        $paciente->save();

        return response()->json($paciente, 201);
    }

    public function show($id)
    {
        return response()->json(Paciente::findOrFail($id), 200);
    }

    public function update(UpdatePatientRequest $request, $id)
    {
        $paciente = Paciente::findOrFail($id);
        $validated = $request->validated();
        $paciente->fill($validated);

        if (isset($validated['nombre']) || isset($validated['apellido'])) {
            $paciente->nombre_completo = trim(
                ($validated['nombre'] ?? $paciente->nombre) . ' ' . ($validated['apellido'] ?? $paciente->apellido)
            );
        }

        $paciente->save();

        return response()->json($paciente, 200);
    }

    public function destroy($id)
    {
        $paciente = Paciente::findOrFail($id);
        $paciente->estado = 'inactivo';
        $paciente->save();
        $paciente->delete();

        return response()->json(['message' => 'Paciente dado de baja'], 200);
    }
}

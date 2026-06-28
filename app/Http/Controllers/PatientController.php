<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientIndexRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

class PatientController extends Controller
{
    public function index(PatientIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = Patient::query()->latest('updated_at');

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

        $patients = $query->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'from' => $patients->firstItem(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'to' => $patients->lastItem(),
                'total' => $patients->total(),
            ],
        ], 200);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['estado'] = 'activo';
        $validated['nombre_completo'] = $validated['nombre'] . ' ' . $validated['apellido'];

        $patient = Patient::create($validated);

        return response()->json([
            'data' => $patient,
            'message' => 'Paciente creado',
        ], 201);
    }

    public function show(int $patient): JsonResponse
    {
        return response()->json([
            'data' => Patient::findOrFail($patient),
        ], 200);
    }

    public function update(UpdatePatientRequest $request, int $patient): JsonResponse
    {
        $patient = Patient::findOrFail($patient);
        $validated = $request->validated();

        $patient->fill($validated);

        if (isset($validated['nombre']) || isset($validated['apellido'])) {
            $patient->nombre_completo = trim(
                ($validated['nombre'] ?? $patient->nombre) . ' ' . ($validated['apellido'] ?? $patient->apellido)
            );
        }

        $patient->save();

        return response()->json([
            'data' => $patient,
            'message' => 'Paciente actualizado',
        ], 200);
    }

    public function destroy(int $patient): JsonResponse
    {
        $patient = Patient::findOrFail($patient);
        $patient->estado = 'inactivo';
        $patient->save();
        $patient->delete();

        return response()->json([
            'message' => 'Paciente dado de baja',
        ], 200);
    }
}

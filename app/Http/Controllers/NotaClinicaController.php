<?php

namespace App\Http\Controllers;

use App\Models\NotaClinica;
use Illuminate\Http\Request;

class NotaClinicaController extends Controller
{
    public function index()
    {
        $notas = NotaClinica::query()
            ->latest('updated_at')
            ->get();

        return response()->json(['data' => $notas]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $nota = NotaClinica::create($validated);

        return response()->json(['data' => $nota, 'message' => 'Nota creada'], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request, true);
        $nota = NotaClinica::findOrFail($id);
        $nota->fill($validated)->save();

        return response()->json(['data' => $nota, 'message' => 'Nota actualizada']);
    }

    public function destroy(int $id)
    {
        NotaClinica::findOrFail($id)->delete();

        return response()->json(['message' => 'Nota eliminada']);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'paciente_id' => ['nullable', 'integer', 'exists:pacientes,id'],
            'paciente_nombre' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'contenido' => [$required, 'nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'pinned' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}

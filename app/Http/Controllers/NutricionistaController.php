<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNutricionistaRequest;
use App\Http\Requests\UpdateNutricionistaRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class NutricionistaController extends Controller
{
    private function getEmpresaId(Request $request)
    {
        return $request->header('X-Empresa-ID');
    }

    public function index(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);

        if (!$empresaId) {
            return response()->json(['message' => 'Falta encabezado X-Empresa-ID'], 400);
        }

        $nutris = User::where('rol', 'nutricionista')->get();

        return response()->json(['data' => $nutris], 200);
    }

    public function store(StoreNutricionistaRequest $request)
    {
        $empresaId = $this->getEmpresaId($request);
        $validated = $request->validated();

        $nutri = User::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'usuario' => $validated['usuario'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'rol' => 'nutricionista',
            'estado' => 1
        ]);

        return response()->json(['data' => $nutri, 'message' => 'Nutricionista creado'], 201);
    }

    public function update(UpdateNutricionistaRequest $request, $id)
    {
        $empresaId = $this->getEmpresaId($request);

        $nutri = User::where('rol', 'nutricionista')->findOrFail($id);
        $validated = $request->validated();

        $nutri->nombre = $validated['nombre'];
        $nutri->apellido = $validated['apellido'];
        $nutri->usuario = $validated['usuario'];
        $nutri->email = $validated['email'];

        if (! empty($validated['password'])) {
            $nutri->password = Hash::make($validated['password']);
        }

        $nutri->save();

        return response()->json(['data' => $nutri, 'message' => 'Nutricionista actualizado']);
    }

    public function destroy(Request $request, $id)
    {
        $empresaId = $this->getEmpresaId($request);

        $nutri = User::where('rol', 'nutricionista')->findOrFail($id);

        $nutri->delete();

        return response()->json(['message' => 'Nutricionista eliminado']);
    }
}

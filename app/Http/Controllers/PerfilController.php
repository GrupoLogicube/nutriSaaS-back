<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PerfilController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:255'],
            'apellido' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'especialidad' => ['nullable', 'string', 'max:255'],
            'password_actual' => ['nullable', 'string'],
            'password_nuevo' => ['nullable', 'string', 'min:8'],
        ]);

        $user = $request->user();

        if (! empty($validated['password_nuevo'])) {
            if (! Hash::check($validated['password_actual'] ?? '', $user->password)) {
                return response()->json(['message' => 'La contrasena actual no es correcta.'], 422);
            }

            $user->password = Hash::make($validated['password_nuevo']);
        }

        foreach (['nombre', 'apellido', 'email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        $user->save();

        return response()->json(['data' => $user, 'message' => 'Perfil actualizado']);
    }
}

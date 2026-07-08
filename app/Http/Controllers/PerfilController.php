<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PerfilController extends Controller
{
    public function show(Request $request)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json(['message' => 'Usuario autenticado no encontrado.'], 401);
        }

        return response()->json(['data' => $user]);
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

        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json(['message' => 'Usuario autenticado no encontrado.'], 401);
        }

        if (! empty($validated['password_nuevo'])) {
            if (! Hash::check($validated['password_actual'] ?? '', $user->password)) {
                return response()->json(['message' => 'La contrasena actual no es correcta.'], 422);
            }

            $user->password = Hash::make($validated['password_nuevo']);
        }

        $connection = $user->getConnectionName() ?: config('database.default');

        foreach (['nombre', 'apellido', 'email', 'telefono', 'especialidad'] as $field) {
            if (array_key_exists($field, $validated) && Schema::connection($connection)->hasColumn('users', $field)) {
                $user->{$field} = $validated[$field];
            }
        }

        $user->save();

        return response()->json(['data' => $user, 'message' => 'Perfil actualizado']);
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $masterAdmin = $request->attributes->get('master_admin');

        if ($masterAdmin && isset($masterAdmin->id)) {
            return User::on('master')->find($masterAdmin->id);
        }

        return null;
    }
}

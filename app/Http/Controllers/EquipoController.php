<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EquipoController extends Controller
{
    public function index(Request $request)
    {
        $currentUserId = optional($request->user())->id;

        $members = User::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => trim("{$user->nombre} {$user->apellido}"),
                'nombre' => trim("{$user->nombre} {$user->apellido}"),
                'email' => $user->email,
                'role' => $this->displayRole($user->rol),
                'rol' => $this->displayRole($user->rol),
                'status' => $user->estado ? 'activo' : 'inactivo',
                'estado' => $user->estado ? 'activo' : 'inactivo',
                'patients' => 0,
                'total_pacientes' => 0,
                'lastLogin' => '---',
                'ultimo_acceso' => '---',
                'isOwner' => $user->id === $currentUserId,
                'es_propietario' => $user->id === $currentUserId,
            ]);

        return response()->json(['data' => $members]);
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $emailName = Str::before($validated['email'], '@');
        $user = User::create([
            'nombre' => Str::headline(str_replace(['.', '_', '-'], ' ', $emailName)),
            'apellido' => '',
            'usuario' => $emailName,
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)),
            'rol' => $this->storageRole($validated['rol'] ?? $validated['role'] ?? 'Nutricionista'),
            'estado' => false,
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->nombre,
                'role' => $this->displayRole($user->rol),
                'status' => 'pendiente',
                'patients' => 0,
                'lastLogin' => '---',
                'isOwner' => false,
            ],
            'message' => 'Invitacion registrada',
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'rol' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable'],
            'status' => ['nullable'],
        ]);

        $user = User::findOrFail($id);

        if (isset($validated['rol']) || isset($validated['role'])) {
            $user->rol = $this->storageRole($validated['rol'] ?? $validated['role']);
        }

        if (array_key_exists('estado', $validated) || array_key_exists('status', $validated)) {
            $estado = $validated['estado'] ?? $validated['status'];
            $user->estado = in_array($estado, [true, 1, '1', 'activo', 'active'], true);
        }

        $user->save();

        return response()->json(['data' => $user, 'message' => 'Miembro actualizado']);
    }

    public function destroy(int $id)
    {
        User::findOrFail($id)->delete();

        return response()->json(['message' => 'Miembro eliminado']);
    }

    private function displayRole(?string $role): string
    {
        return match ($role) {
            'admin', 'super_admin' => 'Administrador',
            'recepcionista' => 'Recepcionista',
            'solo_lectura' => 'Solo lectura',
            default => 'Nutricionista',
        };
    }

    private function storageRole(string $role): string
    {
        return match ($role) {
            'Administrador' => 'super_admin',
            default => 'nutricionista',
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const TENANT_DATABASE_PATTERN = '/^nutrisaas_tenant_[a-f0-9]{32}$/';

    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
            'id_empresa' => 'nullable|integer'
        ]);

        $connection = 'master';
        if ($request->filled('id_empresa')) {
            $empresa = Company::on('master')->find($request->id_empresa);
            if (!$empresa) {
                return response()->json(['message' => 'Empresa no encontrada'], 404);
            }
            config(['database.connections.tenant.database' => $empresa->nombre_bd]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            $connection = 'tenant';
        }

        $user = User::on($connection)->where('usuario', $request->usuario)->first();

        if (! $this->isValidUser($user, $request->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'usuario' => $user->usuario,
                'email' => $user->email,
                'telefono' => $user->telefono ?? null,
                'especialidad' => $user->especialidad ?? null,
                'rol' => $user->rol,
            ]
        ], 200);
    }

    public function authLogin(AuthLoginRequest $request)
    {
        $credentials = $request->validated();

        $masterLogin = $this->attemptMasterLogin($credentials['email'], $credentials['password']);

        if ($masterLogin) {
            return response()->json($masterLogin, 200);
        }

        $tenantLogin = $this->attemptTenantLogin($credentials['email'], $credentials['password']);

        if ($tenantLogin) {
            return response()->json($tenantLogin, 200);
        }

        return response()->json([
            'message' => 'Credenciales incorrectas',
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada correctamente']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'usuario' => $user->usuario,
                'email' => $user->email,
                'rol' => $user->rol,
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $validated = $request->validated();
        $connection = $this->resolvePasswordConnection($validated['tenant_id'] ?? null);

        $user = User::on($connection)->where('email', $validated['email'])->first();

        if ($user) {
            $plainToken = Str::random(64);

            DB::connection($connection)->table('password_reset_tokens')->updateOrInsert(
                ['email' => $validated['email']],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $resetUrl = $this->passwordResetUrl($validated['email'], $plainToken, $validated['tenant_id'] ?? null);

            Mail::raw(
                "Solicitaste restablecer tu contrasena en NutriSaaS.\n\nAbre este enlace para continuar:\n{$resetUrl}\n\nSi no fuiste tu, ignora este correo.",
                fn ($message) => $message
                    ->to($validated['email'])
                    ->subject('Restablecer contrasena - NutriSaaS')
            );
        }

        return response()->json([
            'message' => 'Si el correo existe, enviaremos instrucciones para restablecer la contrasena.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();
        $connection = $this->resolvePasswordConnection($validated['tenant_id'] ?? null);

        $reset = DB::connection($connection)
            ->table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $reset || ! Hash::check($validated['token'], $reset->token)) {
            return response()->json(['message' => 'Token de restablecimiento invalido.'], 422);
        }

        if ($reset->created_at && now()->diffInMinutes($reset->created_at) > 60) {
            return response()->json(['message' => 'Token de restablecimiento expirado.'], 422);
        }

        $user = User::on($connection)->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Token de restablecimiento invalido.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::connection($connection)
            ->table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        $user->tokens()->delete();

        return response()->json(['message' => 'Contrasena actualizada correctamente.']);
    }

    private function attemptMasterLogin(string $email, string $password): ?array
    {
        $user = User::on('master')->where('email', $email)->first();

        if (! $this->isValidUser($user, $password)) {
            return null;
        }

        $company = null;

        if (Schema::connection('master')->hasColumn('companies', 'owner_user_id')) {
            $company = Company::on('master')
                ->where('owner_user_id', $user->id)
                ->where('estado', 'activo')
                ->first();
        }

        if (! $company) {
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->loginPayload($user, $token, $company, 'master');
    }

    private function attemptTenantLogin(string $email, string $password): ?array
    {
        $companies = Company::on('master')
            ->where('estado', 'activo')
            ->whereNotNull('nombre_bd')
            ->get();

        foreach ($companies as $company) {
            if (! $this->isValidTenantDatabaseName((string) $company->nombre_bd)) {
                continue;
            }

            try {
                config(['database.connections.tenant.database' => $company->nombre_bd]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                $user = User::on('tenant')->where('email', $email)->first();

                if (! $this->isValidUser($user, $password)) {
                    continue;
                }

                $token = $user->createToken('auth_token')->plainTextToken;

                return $this->loginPayload($user, $token, $company, 'tenant');
            } catch (Throwable $exception) {
                Log::warning('Tenant login lookup failed.', [
                    'company_id' => $company->id,
                    'tenant_database' => $company->nombre_bd,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                DB::purge('tenant');
            }
        }

        return null;
    }

    private function isValidUser(?User $user, string $password): bool
    {
        return $user
            && (bool) $user->estado
            && Hash::check($password, $user->password);
    }

    private function loginPayload(User $user, string $token, Company $company, string $connection): array
    {
        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'tenant_id' => $company->id,
            'tenant' => [
                'id' => $company->id,
                'nombre' => $company->nombre,
                'nombre_bd' => $company->nombre_bd,
            ],
            'auth_connection' => $connection,
            'user' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'usuario' => $user->usuario,
                'email' => $user->email,
                'telefono' => $user->telefono ?? null,
                'especialidad' => $user->especialidad ?? null,
                'rol' => $user->rol,
            ],
        ];
    }

    private function isValidTenantDatabaseName(string $databaseName): bool
    {
        return preg_match(self::TENANT_DATABASE_PATTERN, $databaseName) === 1;
    }

    private function resolvePasswordConnection(?int $tenantId): string
    {
        if (! $tenantId) {
            return 'master';
        }

        $company = Company::on('master')
            ->where('estado', 'activo')
            ->findOrFail($tenantId);

        if (! $this->isValidTenantDatabaseName((string) $company->nombre_bd)) {
            abort(422, 'Configuracion tenant invalida.');
        }

        config(['database.connections.tenant.database' => $company->nombre_bd]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return 'tenant';
    }

    private function passwordResetUrl(string $email, string $token, ?int $tenantId): string
    {
        $query = http_build_query(array_filter([
            'email' => $email,
            'token' => $token,
            'tenant_id' => $tenantId,
        ], fn ($value) => $value !== null));

        return rtrim(config('app.frontend_url'), '/') . "/restablecer-contrasena?{$query}";
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantSwitchMiddleware
{
    private const TENANT_DATABASE_PATTERN = '/^nutrisaas_tenant_[a-f0-9]{32}$/';

    /**
     * Capture tenant requests and dynamically reconfigure database.connections.tenant.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresaId = $request->header('X-Empresa-ID');
        $empresaId = is_string($empresaId) ? trim($empresaId) : $empresaId;

        if ($empresaId === null || $empresaId === '') {
            return response()->json(['message' => 'Falta el encabezado X-Empresa-ID requerido para esta ruta.'], 401);
        }

        if (! ctype_digit((string) $empresaId)) {
            return response()->json(['message' => 'El encabezado X-Empresa-ID debe ser numerico.'], 422);
        }

        $empresa = Company::on('master')->find((int) $empresaId);

        if (! $empresa) {
            Log::warning('Tenant switch failed: company not found.', [
                'empresa_id' => $empresaId,
            ]);

            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        if ($empresa->estado !== 'activo') {
            Log::warning('Tenant switch failed: company is inactive.', [
                'empresa_id' => $empresa->id,
                'estado' => $empresa->estado,
            ]);

            return response()->json(['message' => 'Empresa inactiva.'], 403);
        }

        $tenantDatabase = (string) $empresa->nombre_bd;

        if (! $this->isValidTenantDatabaseName($tenantDatabase)) {
            Log::error('Tenant switch failed: invalid tenant database name.', [
                'empresa_id' => $empresa->id,
                'tenant_database' => $tenantDatabase,
            ]);

            return response()->json(['message' => 'Configuracion tenant invalida.'], 500);
        }

        if (! $this->tenantDatabaseExists($tenantDatabase)) {
            Log::error('Tenant switch failed: tenant database does not exist.', [
                'empresa_id' => $empresa->id,
                'tenant_database' => $tenantDatabase,
            ]);

            return response()->json(['message' => 'Base de datos tenant no encontrada.'], 500);
        }

        $masterAdmin = $this->masterSuperAdminFromBearerToken($request);
        $originalDefaultConnection = config('database.default');
        $originalTenantDatabase = config('database.connections.tenant.database');
        $originalGuard = config('auth.defaults.guard', 'web');

        try {
            config([
                'database.connections.tenant.database' => $tenantDatabase,
                'database.default' => 'tenant',
            ]);

            DB::purge('tenant');
            DB::reconnect('tenant');
            DB::setDefaultConnection('tenant');

            Log::info('Tenant connection switched.', [
                'empresa_id' => $empresa->id,
                'tenant_database' => $tenantDatabase,
            ]);

            if (! $masterAdmin && ! auth('sanctum')->check()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            Auth::shouldUse('sanctum');

            $request->attributes->set('tenant_company', $empresa);
            $request->attributes->set('tenant_database', $tenantDatabase);
            $request->attributes->set('master_admin', $masterAdmin);

            return $next($request);
        } finally {
            Auth::shouldUse($originalGuard);

            config([
                'database.connections.tenant.database' => $originalTenantDatabase,
                'database.default' => $originalDefaultConnection,
            ]);

            DB::setDefaultConnection($originalDefaultConnection);
            DB::purge('tenant');
        }
    }

    private function isValidTenantDatabaseName(string $databaseName): bool
    {
        return preg_match(self::TENANT_DATABASE_PATTERN, $databaseName) === 1;
    }

    private function tenantDatabaseExists(string $databaseName): bool
    {
        $database = DB::connection('master')->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$databaseName]
        );

        return $database !== null;
    }

    private function masterSuperAdminFromBearerToken(Request $request): ?object
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $parts = explode('|', $token, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            return null;
        }

        $accessToken = DB::connection('master')
            ->table('personal_access_tokens')
            ->where('id', (int) $parts[0])
            ->first();

        if (! $accessToken || ! hash_equals($accessToken->token, hash('sha256', $parts[1]))) {
            return null;
        }

        if ($accessToken->tokenable_type !== User::class) {
            return null;
        }

        $user = DB::connection('master')
            ->table('users')
            ->where('id', $accessToken->tokenable_id)
            ->first();

        if (! $user || $user->rol !== 'super_admin' || ! (bool) $user->estado) {
            return null;
        }

        return $user;
    }
}

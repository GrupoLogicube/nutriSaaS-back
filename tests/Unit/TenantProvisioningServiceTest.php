<?php

namespace Tests\Unit;

use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TenantProvisioningServiceTest extends TestCase
{
    public function test_it_drops_the_tenant_database_when_migrations_fail(): void
    {
        $database = 'nutrisaas_tenant_' . str_repeat('a', 32);

        DB::shouldReceive('connection->statement')
            ->once()
            ->with("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        DB::shouldReceive('purge')->with('tenant')->twice();
        DB::shouldReceive('reconnect')->with('tenant')->once();

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ])
            ->andReturn(1);

        Artisan::shouldReceive('output')->once()->andReturn('boom');
        Log::shouldReceive('error')->once();

        DB::shouldReceive('connection->statement')
            ->once()
            ->with("DROP DATABASE IF EXISTS `{$database}`");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant migrations failed: boom');

        app(TenantProvisioningService::class)->provision($database);
    }

    public function test_it_rejects_database_names_outside_the_managed_prefix(): void
    {
        DB::shouldReceive('connection')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid tenant database name.');

        app(TenantProvisioningService::class)->provision('mysql');
    }
}

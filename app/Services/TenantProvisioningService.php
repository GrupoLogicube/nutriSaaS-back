<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TenantProvisioningService
{
    private const DATABASE_PREFIX = 'nutrisaas_tenant_';

    public function provision(?string $databaseName = null): string
    {
        $databaseName ??= $this->generateDatabaseName();
        $this->assertValidDatabaseName($databaseName);

        try {
            $this->createDatabase($databaseName);
            $this->runBaseMigrations($databaseName);

            return $databaseName;
        } catch (Throwable $exception) {
            $this->rollbackDatabase($databaseName, $exception);

            throw $exception;
        }
    }

    public function dropProvisionedDatabase(string $databaseName, ?Throwable $exception = null): void
    {
        $this->assertValidDatabaseName($databaseName);

        if ($exception) {
            $this->rollbackDatabase($databaseName, $exception);

            return;
        }

        DB::connection('master')->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        DB::purge('tenant');
    }

    private function generateDatabaseName(): string
    {
        return self::DATABASE_PREFIX . str_replace('-', '', (string) Str::uuid());
    }

    private function createDatabase(string $databaseName): void
    {
        DB::connection('master')->statement(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $databaseName
        ));
    }

    private function runBaseMigrations(string $databaseName): void
    {
        config(['database.connections.tenant.database' => $databaseName]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Tenant migrations failed: ' . Artisan::output());
        }
    }

    private function rollbackDatabase(string $databaseName, Throwable $exception): void
    {
        Log::error('Tenant provisioning failed. Rolling back database.', [
            'database' => $databaseName,
            'error' => $exception->getMessage(),
        ]);

        try {
            DB::connection('master')->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        } catch (Throwable $rollbackException) {
            Log::critical('Tenant provisioning rollback failed.', [
                'database' => $databaseName,
                'error' => $rollbackException->getMessage(),
            ]);
        } finally {
            DB::purge('tenant');
        }
    }

    private function assertValidDatabaseName(string $databaseName): void
    {
        if (! preg_match('/^nutrisaas_tenant_[a-f0-9]{32}$/', $databaseName)) {
            throw new RuntimeException('Invalid tenant database name.');
        }
    }
}

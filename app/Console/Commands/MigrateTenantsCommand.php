<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrateTenantsCommand extends Command
{
    private const TENANT_DATABASE_PATTERN = '/^nutrisaas_tenant_[a-f0-9]{32}$/';

    protected $signature = 'tenants:migrate {--company= : ID de empresa especifica} {--force : Ejecutar migraciones en modo force}';

    protected $description = 'Ejecuta las migraciones tenant pendientes en las bases de empresas activas.';

    public function handle(): int
    {
        $query = Company::on('master')
            ->where('estado', 'activo')
            ->whereNotNull('nombre_bd');

        if ($this->option('company')) {
            $query->where('id', (int) $this->option('company'));
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->warn('No hay empresas activas con base tenant configurada.');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $database = (string) $company->nombre_bd;

            if (! preg_match(self::TENANT_DATABASE_PATTERN, $database)) {
                $this->error("Empresa {$company->id}: nombre_bd invalido ({$database}).");

                return self::FAILURE;
            }

            $this->info("Migrando tenant empresa {$company->id} ({$database})...");

            config(['database.connections.tenant.database' => $database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $exitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => (bool) $this->option('force'),
            ]);

            $this->line(Artisan::output());

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        DB::purge('tenant');
        $this->info('Migraciones tenant completadas.');

        return self::SUCCESS;
    }
}

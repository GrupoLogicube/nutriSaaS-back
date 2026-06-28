<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListTenantPatientsCommand extends Command
{
    protected $signature = 'tenants:patients {--company= : ID de empresa especifica}';

    protected $description = 'Lista pacientes almacenados en los tenants activos para diagnostico.';

    public function handle(): int
    {
        $query = Company::on('master')
            ->where('estado', 'activo')
            ->whereNotNull('nombre_bd');

        if ($this->option('company')) {
            $query->where('id', (int) $this->option('company'));
        }

        foreach ($query->get() as $company) {
            $this->info("Empresa {$company->id} - {$company->nombre} ({$company->nombre_bd})");

            config(['database.connections.tenant.database' => $company->nombre_bd]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $patients = DB::connection('tenant')
                ->table('pacientes')
                ->select('id', 'nombre', 'apellido', 'nombre_completo', 'estado', 'deleted_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($patient) => (array) $patient)
                ->all();

            if ($patients === []) {
                $this->line('  Sin pacientes.');
                continue;
            }

            $this->table(['id', 'nombre', 'apellido', 'nombre_completo', 'estado', 'deleted_at'], $patients);
        }

        return self::SUCCESS;
    }
}

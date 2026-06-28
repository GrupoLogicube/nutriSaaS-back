<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::all();

        // Transform to include full logo URL
        $companies->transform(function ($company) {
            $company->logo_url = $company->logo_path ? asset('storage/' . $company->logo_path) : null;
            return $company;
        });

        return response()->json(['data' => $companies], 200);
    }

    public function store(StoreCompanyRequest $request, TenantProvisioningService $tenantProvisioning)
    {
        $validated = $request->validated();
        $logoPath = null;
        $dbName = null;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        try {
            $dbName = $tenantProvisioning->provision();

            $company = DB::connection('master')->transaction(function () use ($validated, $logoPath, $dbName) {
                $ownerUser = User::on('master')->create([
                    'nombre' => $validated['admin_nombre'],
                    'apellido' => $validated['admin_apellido'],
                    'usuario' => $validated['admin_usuario'],
                    'email' => $validated['admin_email'],
                    'password' => Hash::make($validated['admin_password']),
                    'rol' => 'super_admin',
                    'estado' => true,
                ]);

                return Company::on('master')->create([
                    'owner_user_id' => $ownerUser->id,
                    'nombre' => $validated['nombre'],
                    'logo_path' => $logoPath,
                    'nombre_bd' => $dbName,
                    'estado' => 'activo',
                ]);
            });
        } catch (Throwable $exception) {
            if ($dbName) {
                $tenantProvisioning->dropProvisionedDatabase($dbName, $exception);
            }

            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            Log::error('Company approval failed during tenant provisioning.', [
                'company_name' => $validated['nombre'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo crear la base aislada de la clinica.',
            ], 500);
        }

        $company->load('ownerUser');
        $company->logo_url = $company->logo_path ? asset('storage/' . $company->logo_path) : null;

        return response()->json([
            'message' => 'Empresa y super admin creados exitosamente',
            'data' => $company
        ], 201);
    }
}

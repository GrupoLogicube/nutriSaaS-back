<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DietaController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\FoodSearchController;
use App\Http\Controllers\NotaClinicaController;
use App\Http\Controllers\NutricionistaController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientMetricController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RutinaController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Middleware\TenantSwitchMiddleware;

Route::post('/iniciar-sesion', [AuthController::class , 'login'])->name('iniciar-sesion');
Route::post('/autenticacion/iniciar-sesion', [AuthController::class, 'authLogin'])->name('autenticacion.iniciar-sesion');
Route::post('/autenticacion/recuperar-contrasena', [AuthController::class, 'forgotPassword'])->name('autenticacion.recuperar-contrasena');
Route::post('/autenticacion/restablecer-contrasena', [AuthController::class, 'resetPassword'])->name('autenticacion.restablecer-contrasena');

// Public Empresas
Route::get('/empresas', [CompanyController::class , 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/cerrar-sesion', [AuthController::class , 'logout']);
    Route::get('/autenticacion/yo', [AuthController::class, 'me']);
    Route::post('/autenticacion/refrescar', [AuthController::class, 'refresh']);

    // Admin Empresas
    Route::post('/empresas', [CompanyController::class , 'store']);
});

Route::group(['prefix' => 'empresa', 'middleware' => [TenantSwitchMiddleware::class]], function () {
    Route::get('/alimentos/buscar', FoodSearchController::class);

    Route::get('/nutricionistas', [NutricionistaController::class , 'index']);
    Route::post('/nutricionistas', [NutricionistaController::class , 'store']);
    Route::put('/nutricionistas/{id}', [NutricionistaController::class , 'update']);
    Route::delete('/nutricionistas/{id}', [NutricionistaController::class , 'destroy']);

    Route::apiResource('pacientes-clinicos', PatientController::class)->parameters([
        'pacientes-clinicos' => 'paciente',
    ]);
    Route::apiResource('pacientes-clinicos.metricas', PatientMetricController::class)
        ->parameters([
            'pacientes-clinicos' => 'paciente',
            'metricas' => 'metrica',
        ])
        ->only(['index', 'store', 'show', 'destroy']);

    Route::get('/pacientes', [PacienteController::class , 'index']);
    Route::post('/pacientes', [PacienteController::class , 'store']);
    Route::get('/pacientes/{id}', [PacienteController::class , 'show']);
    Route::put('/pacientes/{id}', [PacienteController::class , 'update']);
    Route::delete('/pacientes/{id}', [PacienteController::class , 'destroy']);

    Route::apiResource('citas', CitaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('notas', NotaClinicaController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/dietas', [DietaController::class, 'index']);
    Route::post('/dietas/generar', [DietaController::class, 'generate']);
    Route::post('/dietas/enviar', [DietaController::class, 'sendToPatient']);
    Route::post('/dietas', [DietaController::class, 'store']);
    Route::delete('/dietas/{id}', [DietaController::class, 'destroy']);

    Route::get('/rutinas', [RutinaController::class, 'index']);
    Route::post('/rutinas/generar', [RutinaController::class, 'generate']);
    Route::post('/rutinas/enviar', [RutinaController::class, 'sendToPatient']);
    Route::post('/rutinas', [RutinaController::class, 'store']);
    Route::put('/rutinas/{id}', [RutinaController::class, 'update']);
    Route::delete('/rutinas/{id}', [RutinaController::class, 'destroy']);

    Route::get('/equipo', [EquipoController::class, 'index']);
    Route::post('/equipo/invitar', [EquipoController::class, 'invite']);
    Route::put('/equipo/{id}', [EquipoController::class, 'update']);
    Route::delete('/equipo/{id}', [EquipoController::class, 'destroy']);

    Route::get('/analiticas', [AnalyticsController::class, 'summary']);
    Route::get('/suscripcion', [SuscripcionController::class, 'show']);
    Route::get('/suscripcion/uso', [SuscripcionController::class, 'usage']);
    Route::post('/suscripcion/cambiar', [SuscripcionController::class, 'changePlan']);
    Route::delete('/suscripcion', [SuscripcionController::class, 'cancel']);
    Route::get('/perfil', [PerfilController::class, 'show']);
    Route::put('/perfil', [PerfilController::class, 'update']);
});

// User route for testing
Route::middleware('auth:sanctum')->get('/usuario', function (Request $request) {
    return $request->user();
});

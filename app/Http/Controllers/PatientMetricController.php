<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientMetricRequest;
use App\Models\Patient;
use App\Models\PatientMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PatientMetricController extends Controller
{
    public function index(int $patient): JsonResponse
    {
        $patient = Patient::findOrFail($patient);

        return response()->json([
            'data' => $patient->metrics()->get(),
        ], 200);
    }

    public function store(StorePatientMetricRequest $request, int $patient): JsonResponse
    {
        $patient = Patient::findOrFail($patient);
        $validated = $request->validated();
        $heightMeters = ((float) $validated['height_cm']) / 100;

        $metric = DB::connection('tenant')->transaction(function () use ($patient, $validated, $heightMeters) {
            $metric = PatientMetric::create([
                'patient_id' => $patient->id,
                'measured_at' => $validated['measured_at'] ?? Carbon::today()->toDateString(),
                'weight_kg' => $validated['weight_kg'],
                'height_cm' => $validated['height_cm'],
                'bmi' => round(((float) $validated['weight_kg']) / ($heightMeters * $heightMeters), 2),
                'allergies' => $validated['allergies'] ?? null,
                'activity_level' => $validated['activity_level'] ?? null,
                'bristol_scale' => $validated['bristol_scale'] ?? null,
                'digestive_quality' => $validated['digestive_quality'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $patient->forceFill([
                'peso' => $metric->weight_kg,
                'altura' => $metric->height_cm,
            ])->save();

            return $metric;
        });

        return response()->json([
            'data' => $metric,
            'message' => 'Metricas antropometricas registradas',
        ], 201);
    }

    public function show(int $patient, int $metric): JsonResponse
    {
        Patient::findOrFail($patient);

        return response()->json([
            'data' => PatientMetric::where('patient_id', $patient)->findOrFail($metric),
        ], 200);
    }

    public function destroy(int $patient, int $metric): JsonResponse
    {
        Patient::findOrFail($patient);
        PatientMetric::where('patient_id', $patient)->findOrFail($metric)->delete();

        return response()->json([
            'message' => 'Metrica antropometrica eliminada',
        ], 200);
    }
}

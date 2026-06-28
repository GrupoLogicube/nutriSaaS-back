<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMetric extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'patient_id',
        'measured_at',
        'weight_kg',
        'height_cm',
        'bmi',
        'allergies',
        'activity_level',
        'bristol_scale',
        'digestive_quality',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:2',
            'bristol_scale' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}

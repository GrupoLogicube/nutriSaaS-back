<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paciente extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'nombre',
        'apellido',
        'nombre_completo',
        'cedula',
        'sexo',
        'edad',
        'peso',
        'altura',
        'ocupacion',
        'tipoConsulta',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'edad' => 'integer',
            'peso' => 'decimal:2',
            'altura' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(PatientMetric::class, 'patient_id')->latest('measured_at');
    }
}

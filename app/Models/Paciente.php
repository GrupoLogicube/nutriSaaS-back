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
        'email',
        'telefono',
        'fecha_nacimiento',
        'cedula',
        'sexo',
        'edad',
        'peso',
        'altura',
        'ocupacion',
        'tipoConsulta',
        'estado',
    ];

    protected $appends = [
        'apellidos',
        'sexo_biologico',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'edad' => 'integer',
            'peso' => 'decimal:2',
            'altura' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function getSexoBiologicoAttribute(): ?string
    {
        return $this->sexo;
    }

    public function getApellidosAttribute(): ?string
    {
        return $this->apellido;
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(PatientMetric::class, 'patient_id')->latest('measured_at');
    }
}

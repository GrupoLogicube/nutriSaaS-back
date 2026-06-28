<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dieta extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'paciente_id',
        'nutricionista_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'calorias_objetivo',
        'proteina_objetivo',
        'carbohidratos_objetivo',
        'grasas_objetivo',
        'plan',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'calorias_objetivo' => 'integer',
            'proteina_objetivo' => 'decimal:2',
            'carbohidratos_objetivo' => 'decimal:2',
            'grasas_objetivo' => 'decimal:2',
            'plan' => 'array',
            'deleted_at' => 'datetime',
        ];
    }
}

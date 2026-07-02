<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rutina extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'paciente_id',
        'nutricionista_id',
        'nombre',
        'objetivo',
        'nivel',
        'dias_semana',
        'equipamiento',
        'restricciones',
        'plan',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'dias_semana' => 'integer',
            'plan' => 'array',
            'deleted_at' => 'datetime',
        ];
    }
}

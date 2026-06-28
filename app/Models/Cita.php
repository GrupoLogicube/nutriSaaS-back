<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'paciente_id',
        'nutricionista_id',
        'paciente_nombre',
        'tipo',
        'fecha_hora',
        'duracion_minutos',
        'estado',
        'motivo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'duracion_minutos' => 'integer',
        ];
    }
}

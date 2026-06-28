<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotaClinica extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'notas_clinicas';

    protected $fillable = [
        'paciente_id',
        'nutricionista_id',
        'paciente_nombre',
        'tipo',
        'titulo',
        'contenido',
        'tags',
        'pinned',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'pinned' => 'boolean',
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }
}

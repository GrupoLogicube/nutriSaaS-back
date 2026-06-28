<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFood extends Model
{
    protected $connection = 'tenant';

    protected $table = 'alimentos_custom';

    protected $fillable = [
        'created_by',
        'nombre',
        'categoria',
        'porcion_base',
        'cantidad_base',
        'energia_kcal',
        'proteina_g',
        'grasa_total_g',
        'carbohidratos_g',
        'fibra_g',
        'sodio_mg',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_base' => 'decimal:2',
            'energia_kcal' => 'decimal:2',
            'proteina_g' => 'decimal:2',
            'grasa_total_g' => 'decimal:2',
            'carbohidratos_g' => 'decimal:2',
            'fibra_g' => 'decimal:2',
            'sodio_mg' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}

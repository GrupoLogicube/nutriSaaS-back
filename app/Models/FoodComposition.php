<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodComposition extends Model
{
    protected $connection = 'master';

    protected $fillable = [
        'source',
        'code',
        'name',
        'category',
        'serving_size',
        'unit',
        'energy_kcal',
        'protein_g',
        'fat_g',
        'carbohydrate_g',
        'fiber_g',
        'calcium_mg',
        'iron_mg',
        'sodium_mg',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'serving_size' => 'decimal:2',
            'energy_kcal' => 'decimal:2',
            'protein_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'carbohydrate_g' => 'decimal:2',
            'fiber_g' => 'decimal:2',
            'calcium_mg' => 'decimal:2',
            'iron_mg' => 'decimal:2',
            'sodium_mg' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}

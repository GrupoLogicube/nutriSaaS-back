<?php

namespace App\Services;

use App\Models\CustomFood;
use App\Models\FoodComposition;
use Illuminate\Support\Collection;

class FoodSearchService
{
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        $limit = max(1, min($limit, 50));

        if (mb_strlen($query) < 2) {
            return [
                'query' => $query,
                'total' => 0,
                'data' => [],
            ];
        }

        $perSourceLimit = $limit;
        $globalFoods = $this->searchGlobalFoods($query, $perSourceLimit);
        $customFoods = $this->searchCustomFoods($query, $perSourceLimit);

        $results = $customFoods
            ->concat($globalFoods)
            ->sortBy([
                ['source_priority', 'asc'],
                ['name', 'asc'],
            ])
            ->take($limit)
            ->values()
            ->map(fn (array $food): array => $this->withoutInternalFields($food));

        return [
            'query' => $query,
            'total' => $results->count(),
            'data' => $results->all(),
        ];
    }

    private function searchGlobalFoods(string $query, int $limit): Collection
    {
        return FoodComposition::query()
            ->where('name', 'like', '%' . $this->escapeLike($query) . '%')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (FoodComposition $food): array => [
                'id' => 'global:' . $food->id,
                'source' => 'global',
                'source_label' => 'Global',
                'source_priority' => 2,
                'is_custom' => false,
                'badge' => 'Global',
                'food_id' => $food->id,
                'name' => $food->name,
                'category' => $food->category,
                'serving_size' => $food->serving_size,
                'unit' => $food->unit,
                'energy_kcal' => $food->energy_kcal,
                'protein_g' => $food->protein_g,
                'fat_g' => $food->fat_g,
                'carbohydrate_g' => $food->carbohydrate_g,
                'fiber_g' => $food->fiber_g,
                'sodium_mg' => $food->sodium_mg,
                'origin' => $food->source,
            ]);
    }

    private function searchCustomFoods(string $query, int $limit): Collection
    {
        return CustomFood::query()
            ->where('activo', true)
            ->where('nombre', 'like', '%' . $this->escapeLike($query) . '%')
            ->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn (CustomFood $food): array => [
                'id' => 'custom:' . $food->id,
                'source' => 'custom',
                'source_label' => 'Propio',
                'source_priority' => 1,
                'is_custom' => true,
                'badge' => 'Propio',
                'food_id' => $food->id,
                'name' => $food->nombre,
                'category' => $food->categoria,
                'serving_size' => $food->cantidad_base,
                'unit' => $food->porcion_base,
                'energy_kcal' => $food->energia_kcal,
                'protein_g' => $food->proteina_g,
                'fat_g' => $food->grasa_total_g,
                'carbohydrate_g' => $food->carbohidratos_g,
                'fiber_g' => $food->fibra_g,
                'sodium_mg' => $food->sodio_mg,
                'origin' => 'tenant',
            ]);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function withoutInternalFields(array $food): array
    {
        unset($food['source_priority']);

        return $food;
    }
}

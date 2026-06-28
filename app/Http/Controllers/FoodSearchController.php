<?php

namespace App\Http\Controllers;

use App\Http\Requests\FoodSearchRequest;
use App\Services\FoodSearchService;
use Illuminate\Http\JsonResponse;

class FoodSearchController extends Controller
{
    public function __invoke(FoodSearchRequest $request, FoodSearchService $foodSearch): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $foodSearch->search($validated['q'], (int) ($validated['limit'] ?? 20)),
            200
        );
    }
}

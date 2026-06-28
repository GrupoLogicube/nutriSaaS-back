<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('master')->create('food_compositions', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('serving_size', 10, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('energy_kcal', 10, 2)->nullable();
            $table->decimal('protein_g', 10, 2)->nullable();
            $table->decimal('fat_g', 10, 2)->nullable();
            $table->decimal('carbohydrate_g', 10, 2)->nullable();
            $table->decimal('fiber_g', 10, 2)->nullable();
            $table->decimal('calcium_mg', 10, 2)->nullable();
            $table->decimal('iron_mg', 10, 2)->nullable();
            $table->decimal('sodium_mg', 10, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source', 'code']);
            $table->unique(['source', 'name']);
            $table->index(['source', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('master')->dropIfExists('food_compositions');
    }
};

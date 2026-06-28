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
        Schema::create('alimentos_custom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('categoria')->nullable();
            $table->string('porcion_base')->default('100 g');
            $table->decimal('cantidad_base', 10, 2)->default(100);
            $table->decimal('energia_kcal', 10, 2)->nullable();
            $table->decimal('proteina_g', 10, 2)->nullable();
            $table->decimal('grasa_total_g', 10, 2)->nullable();
            $table->decimal('carbohidratos_g', 10, 2)->nullable();
            $table->decimal('fibra_g', 10, 2)->nullable();
            $table->decimal('sodio_mg', 10, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alimentos_custom');
    }
};

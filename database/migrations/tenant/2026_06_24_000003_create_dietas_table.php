<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dietas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('nutricionista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->unsignedInteger('calorias_objetivo')->nullable();
            $table->decimal('proteina_objetivo', 8, 2)->nullable();
            $table->decimal('carbohidratos_objetivo', 8, 2)->nullable();
            $table->decimal('grasas_objetivo', 8, 2)->nullable();
            $table->json('plan')->nullable();
            $table->string('estado')->default('borrador');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paciente_id', 'estado']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dietas');
    }
};

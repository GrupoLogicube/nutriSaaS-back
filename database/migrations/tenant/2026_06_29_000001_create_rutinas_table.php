<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->nullable()->constrained('pacientes')->nullOnDelete();
            $table->foreignId('nutricionista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('objetivo')->nullable();
            $table->string('nivel')->nullable();
            $table->unsignedTinyInteger('dias_semana')->nullable();
            $table->string('equipamiento')->nullable();
            $table->text('restricciones')->nullable();
            $table->json('plan')->nullable();
            $table->string('estado')->default('borrador');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paciente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutinas');
    }
};

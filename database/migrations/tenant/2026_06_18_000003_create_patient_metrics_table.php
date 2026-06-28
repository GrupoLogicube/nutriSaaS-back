<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('pacientes')->cascadeOnDelete();
            $table->date('measured_at');
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('height_cm', 8, 2);
            $table->decimal('bmi', 5, 2);
            $table->text('allergies')->nullable();
            $table->string('activity_level')->nullable();
            $table->unsignedTinyInteger('bristol_scale')->nullable();
            $table->text('digestive_quality')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_metrics');
    }
};

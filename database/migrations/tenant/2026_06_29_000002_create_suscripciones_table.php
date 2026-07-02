<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->string('plan')->default('starter');
            $table->string('billing')->default('monthly');
            $table->string('estado')->default('activo');
            $table->date('proxima_factura')->nullable();
            $table->json('historial_pagos')->nullable();
            $table->json('metodo_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};

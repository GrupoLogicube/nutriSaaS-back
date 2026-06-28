<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            if (! Schema::hasColumn('pacientes', 'email')) {
                $table->string('email')->nullable()->after('nombre_completo');
            }

            if (! Schema::hasColumn('pacientes', 'telefono')) {
                $table->string('telefono', 50)->nullable()->after('email');
            }

            if (! Schema::hasColumn('pacientes', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('telefono');
            }
        });

        Schema::table('citas', function (Blueprint $table) {
            if (! Schema::hasColumn('citas', 'paciente_nombre')) {
                $table->string('paciente_nombre')->nullable()->after('nutricionista_id');
            }

            if (! Schema::hasColumn('citas', 'tipo')) {
                $table->string('tipo')->default('Presencial')->after('paciente_nombre');
            }

            if (! Schema::hasColumn('citas', 'duracion_minutos')) {
                $table->unsignedSmallInteger('duracion_minutos')->default(60)->after('fecha_hora');
            }
        });

        Schema::table('notas_clinicas', function (Blueprint $table) {
            if (! Schema::hasColumn('notas_clinicas', 'paciente_nombre')) {
                $table->string('paciente_nombre')->nullable()->after('nutricionista_id');
            }

            if (! Schema::hasColumn('notas_clinicas', 'tags')) {
                $table->json('tags')->nullable()->after('contenido');
            }

            if (! Schema::hasColumn('notas_clinicas', 'pinned')) {
                $table->boolean('pinned')->default(false)->after('tags');
            }
        });

        if (Schema::hasColumn('notas_clinicas', 'paciente_id')) {
            DB::statement('ALTER TABLE notas_clinicas MODIFY paciente_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::table('notas_clinicas', function (Blueprint $table) {
            foreach (['pinned', 'tags', 'paciente_nombre'] as $column) {
                if (Schema::hasColumn('notas_clinicas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('citas', function (Blueprint $table) {
            foreach (['duracion_minutos', 'tipo', 'paciente_nombre'] as $column) {
                if (Schema::hasColumn('citas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pacientes', function (Blueprint $table) {
            foreach (['fecha_nacimiento', 'telefono', 'email'] as $column) {
                if (Schema::hasColumn('pacientes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

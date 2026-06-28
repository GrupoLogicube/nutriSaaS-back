<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('master')->table('companies', function (Blueprint $table) {
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });

        $ownerUserId = DB::connection('master')
            ->table('users')
            ->where('rol', 'super_admin')
            ->orderBy('id')
            ->value('id');

        if ($ownerUserId) {
            DB::connection('master')
                ->table('companies')
                ->whereNull('owner_user_id')
                ->update(['owner_user_id' => $ownerUserId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('master')->table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};

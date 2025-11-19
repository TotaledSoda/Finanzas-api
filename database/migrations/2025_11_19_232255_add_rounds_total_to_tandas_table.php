<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tandas', function (Blueprint $table) {
            // Número total de rondas / turnos de la tanda
            $table->unsignedInteger('rounds_total')
                ->default(0)
                ->after('contribution_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tandas', function (Blueprint $table) {
            $table->dropColumn('rounds_total');
        });
    }
};

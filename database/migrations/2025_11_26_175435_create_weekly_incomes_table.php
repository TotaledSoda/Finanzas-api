<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla ya existe, no hagas nada
        if (Schema::hasTable('weekly_incomes')) {
            return;
        }

        Schema::create('weekly_incomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('week_start');
            $table->date('week_end');

            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('spent', 12, 2)->default(0);
            $table->decimal('saved', 12, 2)->default(0);
            $table->decimal('leftover', 12, 2)->default(0);

            $table->timestamps();

            // una semana única por usuario
            $table->unique(['user_id', 'week_start', 'week_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_incomes');
    }
};

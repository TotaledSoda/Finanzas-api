<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 👇 Si la tabla ya existe, no hagas nada
        if (Schema::hasTable('expenses')) {
            return;
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('weekly_income_id')
                ->nullable()
                ->constrained('weekly_incomes')
                ->nullOnDelete();

            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('type', 50)->default('other');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

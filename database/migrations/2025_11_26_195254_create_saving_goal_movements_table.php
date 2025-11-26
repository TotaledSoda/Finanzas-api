<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_goal_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saving_goal_id')
                ->constrained('saving_goals')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->date('date');

            // +positivo = depósito, negativo = retiro
            $table->decimal('amount', 12, 2);

            // deposit | withdraw | auto_from_weekly
            $table->string('type', 30)->default('deposit');

            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_goal_movements');
    }
};

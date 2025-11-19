<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tandas', function (Blueprint $table) {
            $table->id();

            // Dueño/creador de la tanda
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();

            // Aporte por ronda
            $table->decimal('contribution_amount', 10, 2);

            // Número total de rondas (personas)
            $table->unsignedInteger('rounds_total');

            // Fecha de inicio de la tanda
            $table->date('start_date');

            // Frecuencia de pago: weekly, biweekly, monthly
            $table->string('frequency', 20);

            // Monto total de la tanda (contribution_amount * rounds_total)
            $table->decimal('total_amount', 10, 2);

            // Ronda actual (1, 2, 3, ...)
            $table->unsignedInteger('current_round')->default(1);

            // Estado de la tanda: active, finished, cancelled
            $table->string('status', 20)->default('active');

            // Próxima fecha de pago
            $table->date('next_payment_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tandas');
    }
};

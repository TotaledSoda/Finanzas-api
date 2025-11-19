<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // dueño
            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('total_amount', 12, 2)->default(0);        // monto total de la tanda
            $table->decimal('contribution_amount', 12, 2);             // cuánto aporta cada ronda
            $table->unsignedInteger('rounds_total');                   // número de rondas / participantes
            $table->unsignedInteger('current_round')->default(1);      // ronda actual

            $table->date('start_date');                                // inicio
            $table->date('next_payment_date')->nullable();             // próximo pago
            $table->string('frequency')->default('monthly');           // weekly|biweekly|monthly

            $table->enum('status', ['active', 'upcoming', 'finished', 'cancelled'])
                  ->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tandas');
    }
};

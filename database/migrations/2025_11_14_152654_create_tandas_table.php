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

            // Usuario creador / organizador
            $table->foreignId('organizer_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('name');             // Ahorro para Viaje, Fondo de Emergencia...
            $table->text('description')->nullable();

            // Monto total de la tanda (ej: 12,000)
            $table->decimal('total_amount', 12, 2);

            // Aportación por ronda (ej: 1,000)
            $table->decimal('contribution_amount', 12, 2);

            // Número total de rondas (participantes)
            $table->unsignedInteger('total_rounds');

            // Ronda actual (para el progreso 3/12, 8/10, etc.)
            $table->unsignedInteger('current_round')->default(1);

            // Fecha de inicio (primera ronda)
            $table->date('start_date');

            // Próxima fecha de pago/cobro
            $table->date('next_date')->nullable();

            // Frecuencia de pago (para futuro cálculo automático)
            $table->string('frequency')->default('monthly'); // weekly | biweekly | monthly

            // Estado de la tanda
            $table->string('status')->default('active'); // active | upcoming | finished | cancelled

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tandas');
    }
};

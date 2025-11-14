<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Fecha principal del evento
            $table->date('date');

            // Título que se muestra en la agenda
            $table->string('title');

            // Descripción opcional
            $table->text('description')->nullable();

            // Tipo de evento
            // custom = creado a mano
            $table->string('type')->default('custom'); // custom | others si en un futuro ligas directamente

            // Monto opcional (ej: “revisión de presupuesto de $X”)
            $table->decimal('amount', 12, 2)->nullable();

            // Categoría para UI (goal, tanda, bill, card, etc.)
            $table->string('category')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};

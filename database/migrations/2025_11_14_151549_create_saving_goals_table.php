<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_goals', function (Blueprint $table) {
            $table->id();

            // Relación con el usuario dueño de la meta
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Nombre de la meta (Viaje a Cancún, Nuevo coche, etc.)
            $table->string('name');

            // Descripción opcional
            $table->text('description')->nullable();

            // Monto objetivo
            $table->decimal('target_amount', 12, 2);

            // Monto ahorrado hasta ahora
            $table->decimal('current_amount', 12, 2)->default(0);

            // Fecha límite (nullable por si no quiere poner fecha)
            $table->date('deadline')->nullable();

            // Para UI: tipo/categoría/icono (ej: "travel", "car", "laptop")
            $table->string('category')->nullable();

            // Tipo: individual o grupal
            $table->boolean('is_group')->default(false);

            // Estado de la meta
            $table->string('status')->default('active'); // active | completed | cancelled

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_goals');
    }
};

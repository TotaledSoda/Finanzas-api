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

            // Dueño/organizador de la tanda
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();

            // Aportación fija por periodo (lo que pone cada persona cada vuelta)
            $table->decimal('contribution_amount', 12, 2);

            // Número de personas / lugares en la tanda
            $table->unsignedInteger('num_members');

            // Monto total del "cajón"
            $table->decimal('pot_amount', 12, 2);

            // Frecuencia de pago
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly'])
                ->default('weekly');

            // Cuándo arranca la tanda
            $table->date('start_date');

            // Vuelta actual
            $table->unsignedInteger('current_round')->default(1);

            // active | completed | cancelled
            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tandas');
    }
};

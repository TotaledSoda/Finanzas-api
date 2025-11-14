<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();

            // Usuario dueño del recibo
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Nombre del recibo (CFE, Renta, Netflix, etc.)
            $table->string('name');

            // Opcional: proveedor o etiqueta
            $table->string('provider')->nullable();

            // Descripción / nota
            $table->text('description')->nullable();

            // Monto
            $table->decimal('amount', 12, 2);

            // Fecha de vencimiento
            $table->date('due_date');

            // Si ya se pagó
            $table->timestamp('paid_at')->nullable();

            // Estado general del recibo
            $table->string('status')->default('pending'); // pending | paid | cancelled

            // Categoría para UI (electricity, rent, internet, subscription, credit_card, etc.)
            $table->string('category')->nullable();

            // ¿Se carga automáticamente? (domiciliado)
            $table->boolean('auto_debit')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};

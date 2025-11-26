<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Relación polimórfica: Bill, Tanda, Saving, etc.
            $table->morphs('eventable'); // eventable_id, eventable_type

            $table->string('title');          // "Pago de luz", "Tanda semanal", "Ahorro mensual"
            $table->date('date');             // Fecha del evento
            $table->decimal('amount', 15, 2)->nullable(); // Monto asociado
            $table->string('category')->nullable();       // 'bill', 'tanda', 'saving', 'expense'
            $table->string('status')->default('pending'); // 'pending', 'paid', 'skipped', etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_events');
    }
};

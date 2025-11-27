<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanda_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tanda_id')
                ->constrained('tandas')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Para amarrarlo al calendario
            $table->date('due_date')->nullable();

            // Cuándo se pagó realmente
            $table->date('paid_at')->nullable();

            $table->decimal('amount', 12, 2);

            // pending | paid | late
            $table->string('status')->default('pending');

            $table->string('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanda_payments');
    }
};

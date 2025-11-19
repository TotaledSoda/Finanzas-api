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
        Schema::create('tanda_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tanda_id')
                ->constrained('tandas')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Orden en la tanda (en qué ronda cobra)
            $table->unsignedInteger('order')->nullable();

            // owner | member
            $table->string('role', 20)->default('member');

            // pending | active | finished | cancelled
            $table->string('status', 20)->default('active');

            // Fecha en la que se unió
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            // Para evitar duplicados (misma tanda, mismo usuario)
            $table->unique(['tanda_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanda_members');
    }
};

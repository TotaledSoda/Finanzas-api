<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanda_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tanda_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Si es usuario de la app, se puede ligar
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('name');            // Nombre mostrado
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Número de ronda en la que cobra (1,2,3,...)
            $table->unsignedInteger('round_number')->nullable();

            // Si ya cobró su tanda
            $table->boolean('has_collected')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanda_members');
    }
};

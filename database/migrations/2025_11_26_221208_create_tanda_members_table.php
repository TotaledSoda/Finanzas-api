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
        // 👇 Si la tabla ya existe, no vuelvas a crearla
        if (Schema::hasTable('tanda_members')) {
            return;
        }

        Schema::create('tanda_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanda_id')
                ->constrained('tandas')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->unsignedInteger('turn_order');   // orden en que recibe
            $table->boolean('has_received')->default(false);
            $table->date('received_at')->nullable();

            $table->timestamps();

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

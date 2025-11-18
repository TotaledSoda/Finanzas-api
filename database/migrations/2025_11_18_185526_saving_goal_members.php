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
        Schema::create('saving_goal_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saving_goal_id')
                ->constrained('saving_goals')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('role')->default('member'); // owner | member
            $table->decimal('expected_contribution', 12, 2)->nullable(); // cuánto se espera que ponga
            $table->timestamps();

            $table->unique(['saving_goal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_goal_members');
    }
};

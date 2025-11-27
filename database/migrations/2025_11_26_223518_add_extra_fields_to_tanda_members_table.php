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
        Schema::table('tanda_members', function (Blueprint $table) {
            // Solo agregar si no existen, para no tronarla si ya los agregaste a mano
            if (!Schema::hasColumn('tanda_members', 'turn_order')) {
                $table->unsignedInteger('turn_order')
                    ->after('user_id');
            }

            if (!Schema::hasColumn('tanda_members', 'has_received')) {
                $table->boolean('has_received')
                    ->default(false)
                    ->after('turn_order');
            }

            if (!Schema::hasColumn('tanda_members', 'received_at')) {
                $table->date('received_at')
                    ->nullable()
                    ->after('has_received');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tanda_members', function (Blueprint $table) {
            if (Schema::hasColumn('tanda_members', 'turn_order')) {
                $table->dropColumn('turn_order');
            }
            if (Schema::hasColumn('tanda_members', 'has_received')) {
                $table->dropColumn('has_received');
            }
            if (Schema::hasColumn('tanda_members', 'received_at')) {
                $table->dropColumn('received_at');
            }
        });
    }
};

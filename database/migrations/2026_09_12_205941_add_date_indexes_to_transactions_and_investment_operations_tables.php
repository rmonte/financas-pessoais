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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'date']);
        });

        Schema::table('investment_operations', function (Blueprint $table) {
            $table->index(['investment_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
        });

        Schema::table('investment_operations', function (Blueprint $table) {
            $table->dropIndex(['investment_id', 'date']);
            $table->dropIndex(['user_id', 'date']);
        });
    }
};

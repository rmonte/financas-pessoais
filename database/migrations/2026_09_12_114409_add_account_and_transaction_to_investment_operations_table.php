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
        Schema::table('investment_operations', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('investment_id')->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};

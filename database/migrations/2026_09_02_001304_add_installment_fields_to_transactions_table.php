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
            $table->unsignedTinyInteger('installment_number')->nullable()->after('amount');
            $table->unsignedTinyInteger('installment_total')->nullable()->after('installment_number');
            $table->uuid('installment_group_id')->nullable()->after('installment_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['installment_number', 'installment_total', 'installment_group_id']);
        });
    }
};

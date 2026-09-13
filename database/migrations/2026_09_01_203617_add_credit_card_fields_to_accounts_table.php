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
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_credit_card')->default(false)->after('description');
            $table->decimal('credit_limit', 12, 2)->nullable()->after('is_credit_card');
            $table->unsignedTinyInteger('closing_day')->nullable()->after('credit_limit');
            $table->unsignedTinyInteger('due_day')->nullable()->after('closing_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['is_credit_card', 'credit_limit', 'closing_day', 'due_day']);
        });
    }
};

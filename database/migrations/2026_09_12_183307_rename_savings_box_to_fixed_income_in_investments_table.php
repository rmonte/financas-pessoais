<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('investments')->where('type', 'savings_box')->update(['type' => 'fixed_income']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('investments')->where('type', 'fixed_income')->update(['type' => 'savings_box']);
    }
};

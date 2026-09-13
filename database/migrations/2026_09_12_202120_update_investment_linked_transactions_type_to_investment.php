<?php

use App\Enums\InvestmentOperationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->whereNotNull('investment_operation_id')
            ->update(['type' => 'investment']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $outgoing = array_map(fn (InvestmentOperationType $type) => $type->value, InvestmentOperationType::outgoing());

        DB::table('transactions')
            ->join('investment_operations', 'investment_operations.id', '=', 'transactions.investment_operation_id')
            ->where('transactions.type', 'investment')
            ->whereIn('investment_operations.type', $outgoing)
            ->update(['transactions.type' => 'expense']);

        DB::table('transactions')
            ->join('investment_operations', 'investment_operations.id', '=', 'transactions.investment_operation_id')
            ->where('transactions.type', 'investment')
            ->update(['transactions.type' => 'income']);
    }
};

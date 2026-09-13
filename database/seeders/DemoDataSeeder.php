<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic banks, accounts, categories, and transactions for the admin user.
     */
    public function run(): void
    {
        $user = User::firstWhere('email', (string) config('seed.admin_email'));

        if (! $user) {
            return;
        }

        $user->transactions()->delete();
        $user->accounts()->delete();
        $user->categories()->delete();
        $user->banks()->delete();

        $nubank = Bank::create(['user_id' => $user->id, 'name' => 'Nubank', 'code' => '260']);
        $itau = Bank::create(['user_id' => $user->id, 'name' => 'Itaú', 'code' => '341']);
        Bank::create(['user_id' => $user->id, 'name' => 'Banco do Brasil', 'code' => '001']);

        $checking = Account::create([
            'user_id' => $user->id,
            'bank_id' => $nubank->id,
            'name' => 'Conta Corrente',
            'description' => 'Conta usada no dia a dia',
            'initial_balance' => 2500,
            'is_active' => true,
        ]);

        $savings = Account::create([
            'user_id' => $user->id,
            'bank_id' => $itau->id,
            'name' => 'Poupança',
            'description' => 'Reserva de emergência',
            'initial_balance' => 8000,
            'is_active' => true,
        ]);

        $wallet = Account::create([
            'user_id' => $user->id,
            'bank_id' => null,
            'name' => 'Carteira',
            'description' => null,
            'initial_balance' => 150,
            'is_active' => true,
        ]);

        $creditCard = Account::create([
            'user_id' => $user->id,
            'bank_id' => $nubank->id,
            'name' => 'Cartão Nubank',
            'description' => 'Cartão de crédito do dia a dia',
            'initial_balance' => 0,
            'is_active' => true,
            'is_credit_card' => true,
            'credit_limit' => 3000,
            'closing_day' => 5,
            'due_day' => 12,
        ]);

        $incomeCategories = collect([
            'Salário' => CategoryType::Income,
            'Freelance' => CategoryType::Income,
        ])->map(fn (CategoryType $type, string $name) => Category::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]));

        $expenseCategories = collect([
            'Alimentação' => CategoryType::Expense,
            'Transporte' => CategoryType::Expense,
            'Moradia' => CategoryType::Expense,
            'Lazer' => CategoryType::Expense,
            'Saúde' => CategoryType::Expense,
            'Assinaturas' => CategoryType::Expense,
        ])->map(fn (CategoryType $type, string $name) => Category::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]));

        $expenseTemplates = [
            ['Supermercado', 'Alimentação', 80, 320],
            ['Restaurante', 'Alimentação', 40, 150],
            ['iFood', 'Alimentação', 25, 90],
            ['Uber', 'Transporte', 15, 60],
            ['Combustível', 'Transporte', 100, 250],
            ['Aluguel', 'Moradia', 1200, 1200],
            ['Conta de luz', 'Moradia', 90, 180],
            ['Internet', 'Moradia', 100, 100],
            ['Cinema', 'Lazer', 30, 80],
            ['Academia', 'Saúde', 90, 90],
            ['Farmácia', 'Saúde', 20, 120],
            ['Netflix', 'Assinaturas', 39, 39],
            ['Spotify', 'Assinaturas', 21, 21],
        ];

        $accounts = [$checking, $checking, $creditCard, $creditCard, $creditCard, $wallet];

        foreach ([Carbon::now()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->startOfMonth()] as $monthStart) {
            $daysInMonth = $monthStart->daysInMonth;

            // Income
            $this->createTransaction($user, $checking, $incomeCategories['Salário'], TransactionType::Income, 'Salário', $monthStart->copy()->day(min(5, $daysInMonth)), fake()->randomFloat(2, 4500, 5200));

            if (fake()->boolean(70)) {
                $this->createTransaction($user, $checking, $incomeCategories['Freelance'], TransactionType::Income, 'Projeto freelance', $monthStart->copy()->day(min(random_int(12, 20), $daysInMonth)), fake()->randomFloat(2, 400, 1800));
            }

            // Expenses
            foreach ($expenseTemplates as [$description, $categoryName, $min, $max]) {
                if (fake()->boolean(80)) {
                    $this->createTransaction(
                        $user,
                        fake()->randomElement($accounts),
                        $expenseCategories[$categoryName],
                        TransactionType::Expense,
                        $description,
                        $monthStart->copy()->day(random_int(1, $daysInMonth)),
                        $min === $max ? (float) $min : fake()->randomFloat(2, $min, $max)
                    );
                }
            }

            // Transfer into savings
            $this->createTransfer($user, $checking, $savings, $monthStart->copy()->day(min(10, $daysInMonth)), fake()->randomFloat(2, 300, 800));
        }

        // Pay off the previous month's credit card invoice, leaving the current one open/closed.
        $previousInvoice = Invoice::where('account_id', $creditCard->id)
            ->where('closing_date', '<', now())
            ->orderBy('closing_date')
            ->first();

        if ($previousInvoice) {
            $payment = $this->createTransfer($user, $checking, $creditCard, $previousInvoice->due_date->copy()->subDay(), (float) $previousInvoice->total());
            $previousInvoice->update(['payment_transaction_id' => $payment->id]);
        }
    }

    private function createTransaction(User $user, Account $account, Category $category, TransactionType $type, string $description, CarbonInterface $date, float $amount): void
    {
        $invoiceId = null;

        if ($account->is_credit_card && $type === TransactionType::Expense) {
            $invoiceId = Invoice::forAccountAndDate($account, $date)->id;
        }

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'invoice_id' => $invoiceId,
            'type' => $type,
            'description' => $description,
            'date' => $date->toDateString(),
            'amount' => $amount,
        ]);
    }

    private function createTransfer(User $user, Account $from, Account $to, CarbonInterface $date, float $amount): Transaction
    {
        return $user->transactions()->create([
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'category_id' => null,
            'type' => TransactionType::Transfer,
            'description' => $to->is_credit_card ? 'Pagamento da fatura' : 'Transferência para reserva',
            'date' => $date->toDateString(),
            'amount' => $amount,
        ]);
    }
}

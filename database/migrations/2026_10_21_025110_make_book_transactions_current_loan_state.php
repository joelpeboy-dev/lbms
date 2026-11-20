<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('book_loans')
            ->orderBy('id')
            ->chunkById(500, function (Collection $loans): void {
                $transactionsByLoan = DB::table('book_transactions')
                    ->whereIn('book_loan_id', $loans->pluck('id'))
                    ->orderBy('id')
                    ->get()
                    ->groupBy('book_loan_id');

                foreach ($loans as $loan) {
                    $transactions = $transactionsByLoan->get($loan->id, collect());

                    if ($loan->voided_at !== null) {
                        DB::table('book_transactions')->where('book_loan_id', $loan->id)->delete();

                        continue;
                    }

                    $returned = $loan->returned_at !== null;
                    $attributes = [
                        'book_id' => $loan->book_id,
                        'borrower_id' => $loan->borrower_id,
                        'performed_by' => $returned ? $loan->returned_by : $loan->issued_by,
                        'transaction_type' => $returned ? 'returned' : 'borrowed',
                        'transaction_at' => $returned ? $loan->returned_at : $loan->borrowed_at,
                        'fine_amount' => $returned ? $loan->fine_amount : 0,
                        'updated_at' => now(),
                    ];

                    $transaction = $transactions->first();

                    if ($transaction === null) {
                        DB::table('book_transactions')->insert([
                            ...$attributes,
                            'book_loan_id' => $loan->id,
                            'details' => json_encode(['source' => 'migration'], JSON_THROW_ON_ERROR),
                            'created_at' => $loan->borrowed_at,
                        ]);

                        continue;
                    }

                    DB::table('book_transactions')->where('id', $transaction->id)->update($attributes);
                    DB::table('book_transactions')
                        ->where('book_loan_id', $loan->id)
                        ->where('id', '!=', $transaction->id)
                        ->delete();
                }
            });

        Schema::table('book_transactions', function (Blueprint $table): void {
            $table->unique('book_loan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_transactions', function (Blueprint $table): void {
            $table->dropUnique(['book_loan_id']);
        });

        DB::table('book_transactions')->delete();
        DB::table('book_loans')
            ->orderBy('id')
            ->chunkById(500, function (Collection $loans): void {
                $transactions = [];

                foreach ($loans as $loan) {
                    $loanTransactions = [[
                        'book_loan_id' => $loan->id,
                        'book_id' => $loan->book_id,
                        'borrower_id' => $loan->borrower_id,
                        'performed_by' => $loan->issued_by,
                        'transaction_type' => 'borrowed',
                        'transaction_at' => $loan->borrowed_at,
                        'fine_amount' => 0,
                        'details' => json_encode(['source' => 'migration_rollback'], JSON_THROW_ON_ERROR),
                        'created_at' => $loan->borrowed_at,
                        'updated_at' => $loan->borrowed_at,
                    ]];

                    if ($loan->returned_at !== null) {
                        $loanTransactions[] = [
                            ...$loanTransactions[0],
                            'performed_by' => $loan->returned_by,
                            'transaction_type' => 'returned',
                            'transaction_at' => $loan->returned_at,
                            'fine_amount' => $loan->fine_amount,
                            'created_at' => $loan->returned_at,
                            'updated_at' => $loan->returned_at,
                        ];
                    } elseif ($loan->voided_at !== null) {
                        $loanTransactions[] = [
                            ...$loanTransactions[0],
                            'performed_by' => $loan->voided_by,
                            'transaction_type' => 'voided',
                            'transaction_at' => $loan->voided_at,
                            'created_at' => $loan->voided_at,
                            'updated_at' => $loan->voided_at,
                        ];
                    }

                    $transactions = [...$transactions, ...$loanTransactions];
                }

                if ($transactions !== []) {
                    DB::table('book_transactions')->insert($transactions);
                }
            });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_loan_id')->constrained('book_loans')->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_type', 20);
            $table->timestamp('transaction_at');
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_at']);
            $table->index(['borrower_id', 'transaction_at']);
            $table->index(['book_id', 'transaction_at']);
        });

        DB::table('book_loans')
            ->select(['id', 'book_id', 'borrower_id', 'issued_by', 'returned_by', 'borrowed_at', 'returned_at', 'fine_amount'])
            ->orderBy('id')
            ->chunkById(500, function ($loans): void {
                $transactions = [];

                foreach ($loans as $loan) {
                    $transactions[] = [
                        'book_loan_id' => $loan->id,
                        'book_id' => $loan->book_id,
                        'borrower_id' => $loan->borrower_id,
                        'performed_by' => $loan->issued_by,
                        'transaction_type' => 'borrowed',
                        'transaction_at' => $loan->borrowed_at,
                        'fine_amount' => 0,
                        'created_at' => $loan->borrowed_at,
                        'updated_at' => $loan->borrowed_at,
                    ];

                    if ($loan->returned_at !== null) {
                        $transactions[] = [
                            'book_loan_id' => $loan->id,
                            'book_id' => $loan->book_id,
                            'borrower_id' => $loan->borrower_id,
                            'performed_by' => $loan->returned_by,
                            'transaction_type' => 'returned',
                            'transaction_at' => $loan->returned_at,
                            'fine_amount' => $loan->fine_amount,
                            'created_at' => $loan->returned_at,
                            'updated_at' => $loan->returned_at,
                        ];
                    }
                }

                DB::table('book_transactions')->insert($transactions);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_transactions');
    }
};

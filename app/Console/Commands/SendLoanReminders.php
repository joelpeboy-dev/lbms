<?php

namespace App\Console\Commands;

use App\Models\BookLoan;
use App\Notifications\LibraryMessage;
use Illuminate\Console\Command;

class SendLoanReminders extends Command
{
    protected $signature = 'library:send-loan-reminders';

    protected $description = 'Email borrowers about books due soon or already overdue';

    public function handle(): int
    {
        BookLoan::query()
            ->with(['book', 'borrower'])
            ->whereNull('returned_at')
            ->whereNull('voided_at')
            ->whereNull('due_reminded_at')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->chunkById(100, function ($loans): void {
                foreach ($loans as $loan) {
                    $loan->borrower->notify(new LibraryMessage(
                        'Book due soon: '.$loan->book->title,
                        'Your loan of '.$loan->book->title.' is due on '.$loan->due_at->format('F j, Y').'. Please return or renew it with library staff.',
                        route('loans.mine'),
                        'View my loans',
                    ));
                    $loan->update(['due_reminded_at' => now()]);
                }
            });

        BookLoan::query()
            ->with(['book', 'borrower'])
            ->whereNull('returned_at')
            ->whereNull('voided_at')
            ->whereNull('overdue_reminded_at')
            ->where('due_at', '<', now())
            ->chunkById(100, function ($loans): void {
                foreach ($loans as $loan) {
                    $loan->borrower->notify(new LibraryMessage(
                        'Overdue book: '.$loan->book->title,
                        'Your loan of '.$loan->book->title.' was due on '.$loan->due_at->format('F j, Y').'. Please return it as soon as possible. Any applicable fine is confirmed when staff records the return.',
                        route('loans.mine'),
                        'View my loans',
                    ));
                    $loan->update(['overdue_reminded_at' => now()]);
                }
            });

        $this->info('Loan reminder emails sent.');

        return self::SUCCESS;
    }
}

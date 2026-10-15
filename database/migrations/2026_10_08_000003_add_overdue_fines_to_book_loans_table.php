<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_loans', function (Blueprint $table): void {
            $table->decimal('fine_amount', 10, 2)->default(0.00)->after('returned_at');
            $table->timestamp('fine_paid_at')->nullable()->after('fine_amount');
        });
    }

    public function down(): void
    {
        Schema::table('book_loans', function (Blueprint $table): void {
            $table->dropColumn(['fine_amount', 'fine_paid_at']);
        });
    }
};

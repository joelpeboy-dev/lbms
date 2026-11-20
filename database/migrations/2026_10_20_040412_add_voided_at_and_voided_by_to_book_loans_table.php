<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_loans', function (Blueprint $table): void {
            $table->timestamp('voided_at')->nullable()->after('returned_at');
            $table->foreignId('voided_by')->nullable()->after('returned_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('book_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};

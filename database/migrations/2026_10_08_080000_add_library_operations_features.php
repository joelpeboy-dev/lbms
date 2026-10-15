<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('condition_status', 20)->default('available')->index();
        });

        Schema::table('book_loans', function (Blueprint $table): void {
            $table->timestamp('due_reminded_at')->nullable();
            $table->timestamp('overdue_reminded_at')->nullable();
        });

        Schema::create('library_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_audit_logs');

        Schema::table('book_loans', function (Blueprint $table): void {
            $table->dropColumn(['due_reminded_at', 'overdue_reminded_at']);
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropIndex(['condition_status']);
            $table->dropColumn(['barcode', 'condition_status']);
        });
    }
};

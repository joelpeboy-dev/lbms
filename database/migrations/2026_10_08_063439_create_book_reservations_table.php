<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->unique()->constrained('book_loans')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status', 'requested_at']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reservations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['user_id', 'book_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_activities');
    }
};
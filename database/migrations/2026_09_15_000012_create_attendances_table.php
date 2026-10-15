<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('time_in');
            $table->timestamp('time_out')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'time_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
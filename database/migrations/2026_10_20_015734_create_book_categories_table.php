<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $categories = [
            'fiction' => 'Fiction',
            'literature' => 'Literature',
            'romance' => 'Romance',
            'fantasy' => 'Fantasy',
            'self_help' => 'Self-Help',
            'programming' => 'Programming',
            'computer_science' => 'Computer Science',
            'database' => 'Database',
            'networking' => 'Networking',
            'ai' => 'AI',
            'mathematics' => 'Mathematics',
            'science' => 'Science',
            'biology' => 'Biology',
            'chemistry' => 'Chemistry',
            'biography' => 'Biography',
            'history' => 'History',
            'english' => 'English',
        ];
        $timestamp = now();

        DB::table('book_categories')->insert(
            collect($categories)->map(fn (string $name, string $slug): array => [
                'slug' => $slug,
                'name' => $name,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->values()->all(),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('book_categories');
    }
};

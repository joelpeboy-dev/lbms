<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('books')->where('genre', '')->exists()) {
            throw new RuntimeException('Books with an empty genre must be corrected before the genre foreign key can be added.');
        }

        $missingCategories = DB::table('books')
            ->whereNotNull('genre')
            ->distinct()
            ->pluck('genre')
            ->reject(fn (string $genre): bool => DB::table('book_categories')->where('slug', $genre)->exists())
            ->map(fn (string $genre): array => [
                'slug' => $genre,
                'name' => Str::headline(str_replace(['_', '-'], ' ', $genre)),
            ])
            ->values();

        $duplicateNames = $missingCategories
            ->groupBy('name')
            ->filter(fn (Collection $categories): bool => $categories->count() > 1)
            ->keys();

        if ($duplicateNames->isNotEmpty()) {
            throw new RuntimeException('Multiple existing book genres map to the same category name: '.$duplicateNames->implode(', '));
        }

        foreach ($missingCategories as $category) {
            $existingSlug = DB::table('book_categories')->where('name', $category['name'])->value('slug');

            if ($existingSlug !== null) {
                throw new RuntimeException("Book genre [{$category['slug']}] conflicts with category name [{$category['name']}] assigned to slug [{$existingSlug}].");
            }
        }

        if ($missingCategories->isNotEmpty()) {
            $timestamp = now();

            DB::table('book_categories')->insert(
                $missingCategories
                    ->map(fn (array $category): array => [
                        ...$category,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->all(),
            );
        }

        Schema::table('books', function (Blueprint $table): void {
            $table->foreign('genre')
                ->references('slug')
                ->on('book_categories')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropForeign(['genre']);
        });
    }
};

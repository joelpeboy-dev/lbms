<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookActivity;
use App\Models\User;
use Database\Seeders\BookCatalogSeeder;
use Database\Seeders\MangaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_adds_the_original_story_without_duplicates(): void
    {
        $seeder = app(BookCatalogSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseMissing('books', ['slug' => 'clean-code']);
        $this->assertDatabaseMissing('books', ['slug' => 'the-great-gatsby']);
        $this->assertDatabaseHas('books', [
            'slug' => 'the-ember-nest',
            'title' => 'The Ember Nest',
            'genre' => 'fantasy',
            'cover_url' => asset('images/ember-nest-story.svg'),
            'source_label' => 'Original story created for the Library Book Monitoring System',
        ]);

        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('reading.show', 'the-ember-nest'))
            ->assertOk()
            ->assertSee('The Ember Nest')
            ->assertSee('images/ember-nest-story.svg')
            ->assertSee('Animated story scenes')
            ->assertSee('Chapter One: The Warm Stone')
            ->assertSee('It was a home.');
    }

    public function test_students_can_filter_catalog_books_by_genre(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $programmingBook = $this->createBook('programming-sample', 'Programming', 'programming');
        $this->createBook('fantasy-sample', 'Fantasy', 'fantasy');

        $this->actingAs($student)->get(route('reading.index', ['genre' => 'programming']))
            ->assertOk()
            ->assertSee($programmingBook->title)
            ->assertDontSee('Fantasy Sample');
    }

    public function test_manga_catalog_contains_twenty_complete_original_stories(): void
    {
        $seeder = app(MangaCatalogSeeder::class);
        $seeder->run();
        $seeder->run();

        $stories = Book::query()
            ->where('category', 'manga')
            ->where('source_label', 'Original library manga story')
            ->get();
        $this->assertSame(20, $stories->count());
        $this->assertSame(20, Book::query()->where('category', 'manga')->count());
        $this->assertDatabaseMissing('books', ['slug' => 'naruto']);
        $this->assertDatabaseMissing('books', ['slug' => 'one-piece']);
        $this->assertDatabaseMissing('books', ['slug' => 'demon-slayer-kimetsu-no-yaiba']);
        $this->assertDatabaseMissing('books', ['slug' => 'my-hero-academia']);
        $this->assertDatabaseMissing('books', ['slug' => 'dragon-ball']);
        $this->assertDatabaseMissing('books', ['slug' => 'bleach']);
        $this->assertDatabaseMissing('books', ['slug' => 'jujutsu-kaisen']);
        $this->assertDatabaseMissing('books', ['slug' => 'attack-on-titan']);
        $this->assertDatabaseMissing('books', ['slug' => 'haikyu']);
        $this->assertDatabaseMissing('books', ['slug' => 'dr-stone']);

        foreach ($stories as $story) {
            $this->assertTrue(str_contains($story->content, 'Chapter One:'));
            $this->assertTrue(str_contains($story->content, 'Chapter Two:'));
            $this->assertTrue(str_contains($story->content, 'Chapter Three:'));
            $this->assertGreaterThan(700, strlen($story->content));
        }

        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('reading.index', ['category' => 'manga']))
            ->assertOk()
            ->assertSee('The Clockwork Messenger')
            ->assertSee('The Glass Orchard')
            ->assertDontSee('The Ember Nest');

        $this->actingAs($student)->get(route('reading.show', 'the-glass-orchard'))
            ->assertOk()
            ->assertSee('Chapter One: The Silent Trees')
            ->assertSee('Chapter Two: The Buried Spring')
            ->assertSee('Chapter Three: The Orchard\'s Song');
    }

    public function test_manga_catalog_includes_ten_complete_original_short_stories(): void
    {
        $seeder = app(MangaCatalogSeeder::class);
        $seeder->run();
        $seeder->run();

        $stories = Book::query()
            ->where('category', 'manga')
            ->where('source_label', 'Original library manga story')
            ->get();

        $this->assertSame(20, $stories->count());
        $this->assertSame(20, Book::query()->where('category', 'manga')->count());

        foreach ($stories as $story) {
            $this->assertSame(true, str_contains($story->content, 'Chapter One:'));
            $this->assertSame(true, str_contains($story->content, 'Chapter Two:'));
            $this->assertSame(true, str_contains($story->content, 'Chapter Three:'));
            $this->assertSame(true, strlen($story->content) > 700);
        }

        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('reading.show', 'the-clockwork-messenger'))
            ->assertOk()
            ->assertSee('The Clockwork Messenger')
            ->assertSee('Chapter One: The Broken Bell')
            ->assertSee('Chapter Two: The Narrow Pass')
            ->assertSee('Chapter Three: Two Hands on the Rope');
    }

    public function test_manga_seeder_preserves_legacy_books_that_have_reading_history(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $legacyBook = $this->createBook('naruto', 'Naruto', 'fantasy');
        $legacyBook->update(['category' => 'manga']);
        BookActivity::create([
            'user_id' => $student->id,
            'book_id' => $legacyBook->id,
            'type' => 'viewed',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('its borrowing, reservation, or reading history must be preserved');

        app(MangaCatalogSeeder::class)->run();
    }

    private function createBook(string $slug, string $title, string $genre): Book
    {
        return Book::create([
            'slug' => $slug,
            'category' => 'book',
            'genre' => $genre,
            'title' => $title,
            'author' => 'Catalog Author',
            'description' => 'A catalog description.',
            'content' => 'The digital text is not included.',
            'total_copies' => 1,
        ]);
    }
}

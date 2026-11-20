<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_rename_book_categories(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('book-categories.store'), [
            'name' => 'Graphic Fiction',
        ])->assertRedirect(route('book-categories.index'));

        $category = BookCategory::query()->where('name', 'Graphic Fiction')->firstOrFail();
        $originalSlug = $category->slug;

        $this->actingAs($staff)->put(route('book-categories.update', $category->slug), [
            'name' => 'Graphic Novels',
        ])->assertRedirect(route('book-categories.index'));

        $this->assertDatabaseHas('book_categories', [
            'id' => $category->id,
            'slug' => $originalSlug,
            'name' => 'Graphic Novels',
        ]);
    }

    public function test_category_used_by_a_book_cannot_be_deleted(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = BookCategory::query()->where('slug', 'fantasy')->firstOrFail();
        Book::create([
            'slug' => 'category-protected-book',
            'category' => 'book',
            'genre' => $category->slug,
            'title' => 'Category Protected Book',
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => 1,
        ]);

        $this->actingAs($staff)->delete(route('book-categories.destroy', $category->slug))
            ->assertSessionHasErrors([
                'category' => 'A category assigned to books cannot be deleted.',
            ]);

        $this->assertModelExists($category);
    }

    public function test_books_and_genre_categories_have_matching_relationships(): void
    {
        $category = BookCategory::query()->where('slug', 'fantasy')->firstOrFail();
        $book = Book::create([
            'slug' => 'genre-relationship-book',
            'category' => 'book',
            'genre' => $category->slug,
            'title' => 'Genre Relationship Book',
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
        ]);

        $this->assertSame($category->id, $book->genreCategory->id);
        $this->assertSame(true, $category->books->contains('id', $book->id));
    }

    public function test_staff_can_delete_an_unused_book_category(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = BookCategory::create([
            'slug' => 'unused-category',
            'name' => 'Unused Category',
        ]);

        $this->actingAs($staff)->delete(route('book-categories.destroy', $category->slug))
            ->assertRedirect(route('book-categories.index'));

        $this->assertDatabaseMissing('book_categories', ['slug' => 'unused-category']);
    }

    public function test_book_management_accepts_a_category_created_in_the_database(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = BookCategory::create([
            'slug' => 'graphic-fiction',
            'name' => 'Graphic Fiction',
        ]);

        $this->actingAs($staff)->post(route('books.store'), [
            'category' => 'book',
            'genre' => $category->slug,
            'title' => 'New Category Book',
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => 1,
            'condition_status' => 'available',
        ])->assertRedirect(route('books.index'));

        $this->assertDatabaseHas('books', [
            'title' => 'New Category Book',
            'genre' => 'graphic-fiction',
        ]);
    }

    public function test_students_cannot_manage_book_categories(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('book-categories.index'))->assertForbidden();
    }

    public function test_api_category_listing_preserves_sections_and_includes_managed_categories(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->apiTokenFor($student);

        $this->withToken($token)->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'book')
            ->assertJsonPath('book_categories.0.id', 'ai')
            ->assertJsonPath('book_categories.0.name', 'AI');
    }

    public function test_staff_can_create_categories_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $this->apiTokenFor($staff);

        $response = $this->withToken($token)->postJson('/api/categories', [
            'name' => 'Graphic Fiction',
        ])->assertCreated();

        $this->assertDatabaseHas('book_categories', [
            'name' => 'Graphic Fiction',
            'slug' => $response->json('data.id'),
        ]);
    }

    public function test_staff_can_read_and_update_a_category_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $this->apiTokenFor($staff);
        $category = BookCategory::query()->where('slug', 'fantasy')->firstOrFail();

        $this->withToken($token)->getJson('/api/categories/'.$category->slug)
            ->assertOk()
            ->assertJsonPath('data.id', 'fantasy')
            ->assertJsonPath('data.name', 'Fantasy');

        $this->withToken($token)->putJson('/api/categories/'.$category->slug, [
            'name' => 'Speculative Fiction',
        ])->assertOk()
            ->assertJsonPath('data.id', 'fantasy')
            ->assertJsonPath('data.name', 'Speculative Fiction');

        $this->assertDatabaseHas('book_categories', [
            'slug' => 'fantasy',
            'name' => 'Speculative Fiction',
        ]);
    }

    public function test_staff_can_delete_an_unused_category_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $this->apiTokenFor($staff);
        $category = BookCategory::query()->where('slug', 'fantasy')->firstOrFail();

        $this->withToken($token)->deleteJson('/api/categories/'.$category->slug)
            ->assertOk()
            ->assertJsonPath('message', 'Book category deleted successfully.');

        $this->assertDatabaseMissing('book_categories', ['slug' => 'fantasy']);
    }

    public function test_students_cannot_create_categories_through_the_api(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->apiTokenFor($student);

        $this->withToken($token)->postJson('/api/categories', [
            'name' => 'Restricted Category',
        ])->assertForbidden();

        $this->assertDatabaseMissing('book_categories', ['name' => 'Restricted Category']);
    }

    public function test_api_cannot_delete_a_category_assigned_to_a_book(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $this->apiTokenFor($staff);
        $category = BookCategory::query()->where('slug', 'fantasy')->firstOrFail();
        Book::create([
            'slug' => 'api-category-protected-book',
            'category' => 'book',
            'genre' => $category->slug,
            'title' => 'API Category Protected Book',
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => 1,
        ]);

        $this->withToken($token)->deleteJson('/api/categories/'.$category->slug)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A category assigned to books cannot be deleted.');
    }

    private function apiTokenFor(User $user): string
    {
        return $user->createToken('Category management tests', ['*'], now()->addDay())->plainTextToken;
    }
}

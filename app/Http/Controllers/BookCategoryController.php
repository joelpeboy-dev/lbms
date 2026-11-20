<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\LibraryAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookCategoryController extends Controller
{
    public function index(): View
    {
        return view('book-categories.index', [
            'categories' => BookCategory::query()
                ->withCount('books')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('book_categories', 'name')],
        ]);
        $baseSlug = Str::slug($data['name']) ?: 'category';
        $category = BookCategory::create([
            'name' => $data['name'],
            'slug' => $baseSlug.'-'.Str::lower(Str::random(6)),
        ]);
        LibraryAuditLog::record('book_category.created', $category, ['name' => $category->name]);

        return redirect()->route('book-categories.index')->with('success', 'Book category created.');
    }

    public function update(Request $request, BookCategory $bookCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('book_categories', 'name')->ignore($bookCategory->id),
            ],
        ]);

        $bookCategory->update($data);
        LibraryAuditLog::record('book_category.updated', $bookCategory, ['changed_fields' => array_keys($bookCategory->getChanges())]);

        return redirect()->route('book-categories.index')->with('success', 'Book category updated.');
    }

    public function destroy(BookCategory $bookCategory): RedirectResponse
    {
        if (Book::query()->where('genre', $bookCategory->slug)->exists()) {
            throw ValidationException::withMessages([
                'category' => 'A category assigned to books cannot be deleted.',
            ]);
        }

        LibraryAuditLog::record('book_category.deleted', $bookCategory, ['name' => $bookCategory->name]);
        $bookCategory->delete();

        return redirect()->route('book-categories.index')->with('success', 'Book category deleted.');
    }
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books | Library Book Monitoring System</title>
    <style>
        :root { --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 32px; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), #05070d; color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        main { width: min(100%, 980px); margin: auto; }
        header { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .eyebrow { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 8px 0; font-size: clamp(32px, 6vw, 52px); } p { color: var(--muted); line-height: 1.6; }
        a { display: inline-block; padding: 11px 15px; border-radius: 8px; background: var(--blue); color: white; font-weight: 800; text-decoration: none; }
        a:hover { background: var(--bright); } .secondary { background: var(--red); }
        .notice { color: #9ed4a7; } .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        article { padding: 20px; background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 12px; } .cover { width: 100%; height: 210px; object-fit: cover; margin-bottom: 16px; border-radius: 10px; background: #111a31; } h2 { margin: 0 0 8px; font-size: 22px; } .author { color: var(--red); font-weight: 800; }
        article a { margin-top: 12px; } .search { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; } .search input, .search select { min-width: 180px; padding: 11px; border: 1px solid #3774ff66; border-radius: 8px; background: #0b1020; color: var(--ink); font: inherit; } .search button, article button { margin-top: 12px; padding: 11px 15px; border: 0; border-radius: 8px; background: var(--red); color: white; font: inherit; font-weight: 800; cursor: pointer; } .muted { color: var(--muted); } .card-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; } .card-actions form { margin: 0; } .pagination { margin-top: 24px; } .pagination a { margin-right: 6px; }
        @media (max-width: 760px) { body { padding: 20px; } header { align-items: start; flex-direction: column; } .grid { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <header>
            <div><div class="eyebrow">{{ ucfirst(auth()->user()->role) }} book management</div><h1>Library books</h1><p>Add stories and manga to the reading room.</p></div>
            <div><a class="secondary" href="{{ route('books.create') }}">Add new book</a> <a href="{{ route('loans.index') }}">Manage borrowing</a> <a href="{{ url('/' . auth()->user()->role . '/dashboard') }}">Dashboard</a></div>
        </header>
        @if (session('success')) <p class="notice">{{ session('success') }}</p> @endif
        @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
        <form class="search" method="GET" action="{{ route('books.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search titles, authors, genres, or scan a barcode" aria-label="Search titles, authors, genres, or barcode" autofocus>
            <select name="category" aria-label="Filter by section"><option value="">All sections</option>@foreach (['book' => 'Other books', 'study' => 'Study', 'manga' => 'Manga'] as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="genre" aria-label="Filter by genre"><option value="">All genres</option>@foreach (config('library.genres') as $value => $label)<option value="{{ $value }}" @selected(($filters['genre'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button type="submit">Search</button>
        </form>
        <section class="grid">
            @forelse ($books as $book)
                @php($available = $book->condition_status === 'available' ? max(0, $book->total_copies - $book->active_loans_count) : 0)
                <article>@if ($book->cover_url)<img class="cover" src="{{ $book->cover_url }}" alt="Cover of {{ $book->title }}" loading="lazy">@else<div class="cover" aria-label="No cover available"></div>@endif<div class="author">{{ strtoupper($book->category) }}@if ($book->genre) · {{ config('library.genres.' . $book->genre, $book->genre) }}@endif</div><h2>{{ $book->title }}</h2><p>By {{ $book->author }}</p><p class="muted">{{ $available }} of {{ $book->total_copies }} copies available · {{ ucfirst($book->condition_status) }}@if ($book->barcode) · Barcode {{ $book->barcode }}@endif</p><div class="card-actions"><a href="{{ route('reading.show', $book->slug) }}">Read</a><a href="{{ route('books.edit', $book) }}">Edit</a><form method="POST" action="{{ route('books.destroy', $book) }}" onsubmit="return confirm('Delete this book?')">@csrf @method('DELETE')<button type="submit">Delete</button></form></div></article>
            @empty
                <p>No titles match your search.</p>
            @endforelse
        </section>
        <div class="pagination">{{ $books->links() }}</div>
    </main>
</body>
</html>

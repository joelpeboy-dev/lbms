<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books | Library Book Monitoring System</title>
    <style>
        :root { --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 32px; position: relative; overflow-x: hidden; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), radial-gradient(circle at 85% 80%, #e23d4f26, transparent 28%), #05070d; color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        body::before, body::after { content: ''; position: fixed; z-index: -1; width: 280px; height: 280px; border-radius: 50%; filter: blur(8px); opacity: .45; pointer-events: none; animation: drift 14s ease-in-out infinite alternate; }
        body::before { top: -130px; right: 8%; background: #3774ff33; } body::after { bottom: -150px; left: 8%; background: #e23d4f2b; animation-delay: -5s; }
        main { width: min(100%, 980px); margin: auto; animation: reveal .65s ease both; }
        header { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .eyebrow { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 8px 0; font-size: clamp(32px, 6vw, 52px); } p { color: var(--muted); line-height: 1.6; }
        .section-heading { display: flex; justify-content: space-between; align-items: baseline; margin: 34px 0 14px; color: var(--ink); font-size: 24px; font-weight: 800; } .section-heading span:last-child { color: var(--muted); font-size: 13px; font-weight: 400; }
        .catalog-filter { display: flex; flex-wrap: wrap; gap: 10px; margin: 24px 0; } .catalog-filter input, .catalog-filter select { min-width: 180px; padding: 11px; border: 1px solid #3774ff66; border-radius: 8px; background: #0b1020; color: var(--ink); font: inherit; } .catalog-filter button { padding: 11px 15px; border: 0; border-radius: 8px; background: var(--blue); color: white; font-weight: 800; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        article { padding: 18px; min-height: 420px; display: flex; flex-direction: column; background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 16px; box-shadow: 0 20px 50px #0008; animation: cardIn .55s ease both; }
        .cover { width: 100%; height: 210px; object-fit: cover; border-radius: 10px; background: #111a31; }
        .genre { margin-top: 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        article:nth-child(2) { animation-delay: .1s; } article:nth-child(3) { animation-delay: .2s; }
        h2 { margin: 12px 0 6px; font-size: 21px; } .author { color: var(--red); font-size: 13px; font-weight: 800; }
        article a, header a { display: inline-block; margin-top: auto; padding: 11px 15px; border-radius: 8px; background: var(--blue); color: white; font-weight: 800; text-decoration: none; transition: transform .2s, background .2s; }
        article a:hover, header a:hover { transform: translateY(-3px); background: var(--bright); }
        article form { margin-top: 12px; }
        article form button { width: 100%; padding: 11px 15px; border: 0; border-radius: 8px; background: var(--blue); color: white; font: inherit; font-weight: 800; cursor: pointer; }
        header a { margin-top: 0; white-space: nowrap; }
        @keyframes reveal { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @keyframes cardIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } } @keyframes drift { from { transform: translate3d(-18px, 8px, 0) scale(.9); } to { transform: translate3d(22px, -18px, 0) scale(1.08); } }
        @media (max-width: 760px) { body { padding: 20px; } header { align-items: start; flex-direction: column; } .grid { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <header>
            <div><div class="eyebrow">Library reading room</div><h1>Browse books</h1><p>Read stories, mathematics, manga, and other books from the library collection.</p></div>
            <a href="{{ url('/' . auth()->user()->role . '/dashboard') }}">Back to dashboard</a>
        </header>
        <form class="catalog-filter" method="GET" action="{{ route('reading.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Search by title, author, description, or scan a barcode" aria-label="Search the library by title, author, description, or barcode" autofocus>
            <select name="category" aria-label="Filter by section"><option value="">All sections</option>@foreach (['book' => 'Other books', 'study' => 'Study', 'manga' => 'Manga'] as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="genre" aria-label="Filter by genre"><option value="">All genres</option>@foreach (config('library.genres') as $value => $label)<option value="{{ $value }}" @selected(($filters['genre'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button type="submit">Search</button>
        </form>
        <section>
            <div class="section-heading"><span>Other Books</span><span>{{ $books->count() }} titles</span></div>
            <div class="grid">
            @forelse ($books as $book)
                <article>
                    @if ($book->cover_url)<img class="cover" src="{{ $book->cover_url }}" alt="Cover of {{ $book->title }}" loading="lazy">@else<div class="cover"></div>@endif
                    <div class="author">BOOK {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                    @if ($book->genre)<div class="genre">{{ config('library.genres.' . $book->genre, $book->genre) }}</div>@endif
                    <h2>{{ $book->title }}</h2>
                    <p>By {{ $book->author }}</p>
                    <p>{{ $book->description }}</p>
                    @php($available = $book->condition_status === 'available' ? max(0, $book->total_copies - $book->active_loans_count) : 0)
                    <p>{{ $available }} of {{ $book->total_copies }} physical copies available</p>
                    <a href="{{ route('reading.show', $book->slug) }}">Read book</a>
                    @include('student.reservation-action', ['book' => $book, 'available' => $available])
                </article>
            @empty
                <p>No books have been added yet.</p>
            @endforelse
            </div>
        </section>
        <section>
            <div class="section-heading"><span>Study</span><span>{{ $study->count() }} titles</span></div>
            <div class="grid">
            @forelse ($study as $book)
                <article>
                    @if ($book->cover_url)<img class="cover" src="{{ $book->cover_url }}" alt="Cover of {{ $book->title }}" loading="lazy">@else<div class="cover"></div>@endif
                    <div class="author">STUDY</div>
                    @if ($book->genre)<div class="genre">{{ config('library.genres.' . $book->genre, $book->genre) }}</div>@endif
                    <h2>{{ $book->title }}</h2>
                    <p>By {{ $book->author }}</p>
                    <p>{{ $book->description }}</p>
                    @php($available = $book->condition_status === 'available' ? max(0, $book->total_copies - $book->active_loans_count) : 0)
                    <p>{{ $available }} of {{ $book->total_copies }} physical copies available</p>
                    <a href="{{ route('reading.show', $book->slug) }}">Study guide</a>
                    @include('student.reservation-action', ['book' => $book, 'available' => $available])
                </article>
            @empty
                <p>No study guides have been added yet.</p>
            @endforelse
            </div>
        </section>
        <section>
            <div class="section-heading"><span>Manga</span><span>{{ $manga->count() }} titles</span></div>
            <div class="grid">
            @forelse ($manga as $book)
                <article>
                    @if ($book->cover_url)<img class="cover" src="{{ $book->cover_url }}" alt="Cover of {{ $book->title }}" loading="lazy">@else<div class="cover"></div>@endif
                    <div class="author">MANGA {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                    @if ($book->genre)<div class="genre">{{ config('library.genres.' . $book->genre, $book->genre) }}</div>@endif
                    <h2>{{ $book->title }}</h2>
                    <p>By {{ $book->author }}</p>
                    <p>{{ $book->description }}</p>
                    @php($available = $book->condition_status === 'available' ? max(0, $book->total_copies - $book->active_loans_count) : 0)
                    <p>{{ $available }} of {{ $book->total_copies }} physical copies available</p>
                    <a href="{{ route('reading.show', $book->slug) }}">Read manga</a>
                    @include('student.reservation-action', ['book' => $book, 'available' => $available])
                </article>
            @empty
                <p>No manga have been added yet.</p>
            @endforelse
            </div>
        </section>
    </main>
</body>
</html>
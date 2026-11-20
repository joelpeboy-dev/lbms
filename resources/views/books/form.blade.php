<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} | Library Book Monitoring System</title>
    <style>
        :root { --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; }
        * { box-sizing: border-box; } body { margin: 0; min-height: 100vh; padding: 32px; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), #05070d; color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        main { width: min(100%, 760px); margin: auto; padding: 36px; background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 16px; }
        .eyebrow { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; } h1 { margin: 8px 0 24px; font-size: clamp(32px, 6vw, 52px); }
        label { display: block; margin-top: 16px; color: var(--muted); font-weight: 800; } input, select, textarea { width: 100%; margin-top: 7px; padding: 12px; border: 1px solid #3774ff66; border-radius: 8px; background: #05070d; color: var(--ink); font: inherit; } textarea { min-height: 140px; resize: vertical; }
        .error { color: #ff9ba6; } .actions { margin-top: 24px; display: flex; gap: 10px; } button, a { padding: 12px 17px; border: 0; border-radius: 8px; background: var(--blue); color: white; font: 800 14px 'Trebuchet MS', sans-serif; text-decoration: none; cursor: pointer; } button:hover, a:hover { background: var(--bright); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <div class="eyebrow">Staff and admin only</div><h1>{{ $heading }}</h1>
        @if ($errors->any()) <div class="error"><p>Please correct the fields below.</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="POST" action="{{ $action }}">
            @csrf
            @isset($book) @method('PUT') @endisset
            <label>Section<select name="category" required><option value="book" @selected(old('category', $book->category ?? 'book') === 'book')>Other book</option><option value="study" @selected(old('category', $book->category ?? '') === 'study')>Study</option><option value="manga" @selected(old('category', $book->category ?? '') === 'manga')>Manga</option></select></label>
            <label>Genre<select name="genre"><option value="">No specific genre</option>@foreach ($genres as $value => $label)<option value="{{ $value }}" @selected(old('genre', $book->genre ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Title<input name="title" value="{{ old('title', $book->title ?? '') }}" required maxlength="255"></label>
            <label>Author<input name="author" value="{{ old('author', $book->author ?? '') }}" required maxlength="255"></label>
            <label>Description<textarea name="description" required>{{ old('description', $book->description ?? '') }}</textarea></label>
            <label>Total physical copies<input type="number" name="total_copies" value="{{ old('total_copies', $book->total_copies ?? 1) }}" min="1" max="100000" required></label>
            <label>Barcode / ISBN<input name="barcode" value="{{ old('barcode', $book->barcode ?? '') }}" maxlength="64" autocomplete="off"></label>
            <label>Inventory condition (applies to all copies under this title)
                <select name="condition_status" required>
                    @foreach (['available' => 'Available', 'maintenance' => 'Maintenance', 'damaged' => 'Damaged', 'lost' => 'Lost'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition_status', $book->condition_status ?? 'available') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Full story or manga text<textarea name="content" required>{{ old('content', $book->content ?? '') }}</textarea></label>
            <label>Cover image URL<input type="url" name="cover_url" value="{{ old('cover_url', $book->cover_url ?? '') }}"></label>
            <label>Illustration URLs <span>(one URL per line)</span><textarea name="illustrations" rows="4">{{ old('illustrations', isset($book) ? collect($book->illustrations)->pluck('url')->implode("\n") : '') }}</textarea></label>
            <label>Source label<input name="source_label" value="{{ old('source_label', $book->source_label ?? '') }}" maxlength="255"></label>
            <label>Source URL<input type="url" name="source_url" value="{{ old('source_url', $book->source_url ?? '') }}"></label>
            <div class="actions"><button type="submit">{{ isset($book) ? 'Save changes' : 'Add to reading room' }}</button><a href="{{ route('books.index') }}">Cancel</a></div>
        </form>
    </main>
</body>
</html>

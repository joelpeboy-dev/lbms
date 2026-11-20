<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My reading | Library Book Monitoring System</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif}main{max-width:1000px;margin:auto}.top,.section{padding:22px;border:1px solid #31557f;border-radius:14px;background:#111d31}.section{margin-top:16px}.items{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;padding:0;list-style:none}.items li{padding:14px;border:1px solid #31557f;border-radius:10px}.items a{color:#9ec9ff;font-weight:700}.muted{color:#aeb9d4}.links{display:flex;gap:10px;flex-wrap:wrap}.links a{color:#9ec9ff}@media(max-width:600px){body{padding:12px}}
    </style>
</head>
<body>
<main>
    <section class="top"><p class="muted">Student reading profile</p><h1>My reading</h1><p class="muted">Your recent books, favorites, and suggestions based on genres you have explored.</p><div class="links"><a href="{{ route('reading.index') }}">Browse library</a><a href="{{ route('loans.mine') }}">My loans</a><a href="{{ url('/student/dashboard') }}">Dashboard</a></div></section>
    <section class="section"><h2>Recommended for you</h2><ul class="items">@forelse($recommendations as $book)<li><a href="{{ route('reading.show', $book->slug) }}">{{ $book->title }}</a><p class="muted">{{ $book->author }} · {{ $book->genre ? ($genres[$book->genre] ?? $book->genre) : 'Library pick' }}</p><small>{{ $book->loans_count }} previous loans</small></li>@empty<li>No recommendations yet. Browse books to build your reading profile.</li>@endforelse</ul></section>
    <section class="section"><h2>Favorites</h2><ul class="items">@forelse($favorites as $favorite)<li><a href="{{ route('reading.show', $favorite->book->slug) }}">{{ $favorite->book->title }}</a><p class="muted">{{ $favorite->book->author }}</p></li>@empty<li>No favorites yet.</li>@endforelse</ul></section>
    <section class="section"><h2>Recently viewed</h2><ul class="items">@forelse($recent as $activity)<li><a href="{{ route('reading.show', $activity->book->slug) }}">{{ $activity->book->title }}</a><p class="muted">Viewed {{ $activity->updated_at->format('M j, Y g:i A') }}</p></li>@empty<li>No reading history yet.</li>@endforelse</ul>{{ $recent->links() }}</section>
</main>
</body>
</html>

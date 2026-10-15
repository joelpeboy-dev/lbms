<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $book->title }} | Library Book Monitoring System</title>
    <style>
        :root { --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; position: relative; overflow-x: hidden; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), #05070d; color: var(--ink); font-family: Georgia, 'Times New Roman', serif; }
        body::before { content: ''; position: fixed; z-index: -1; width: 360px; height: 360px; top: -170px; right: 10%; border-radius: 50%; background: #3774ff2e; filter: blur(10px); animation: drift 15s ease-in-out infinite alternate; pointer-events: none; }
        main { width: min(100%, 780px); padding: clamp(28px, 7vw, 70px); background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 20px; box-shadow: 0 24px 70px #000; animation: reveal .65s ease both; }
        .book-head { display: grid; grid-template-columns: 150px 1fr; gap: 28px; align-items: center; } .cover { width: 150px; height: 220px; object-fit: cover; border-radius: 10px; box-shadow: 0 14px 28px #0008; }
        .eyebrow { color: var(--red); font: 800 12px 'Trebuchet MS', sans-serif; letter-spacing: 2px; text-transform: uppercase; } h1 { margin: 14px 0 8px; font-size: clamp(32px, 6vw, 52px); } .author { color: var(--muted); font: 15px 'Trebuchet MS', sans-serif; } .text { margin-top: 42px; color: #dbe4ff; font-size: 20px; line-height: 1.8; white-space: pre-line; } .gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 34px; } .scene { margin: 0; } .scene img { width: 100%; height: 170px; object-fit: cover; border-radius: 10px; } figcaption { margin-top: 8px; color: var(--muted); font: 13px/1.4 'Trebuchet MS', sans-serif; } .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; } button, a { display: inline-block; padding: 12px 17px; border: 0; border-radius: 8px; background: var(--blue); color: white; font: 800 14px 'Trebuchet MS', sans-serif; text-decoration: none; cursor: pointer; transition: transform .2s, background .2s; } button:hover, a:hover { transform: translateY(-3px); background: var(--bright); } .actions button:nth-child(2) { background: var(--red); } .actions button:nth-child(2):hover { background: #bd2639; } .status { min-height: 20px; margin: 12px 0 0; color: #8eafff; font: 14px 'Trebuchet MS', sans-serif; }
        .ember-book .book-head { grid-template-columns: 1fr; }
        .ember-animation { margin-top: 30px; overflow: hidden; border: 1px solid #3774ff66; border-radius: 16px; background: #091222; box-shadow: 0 18px 40px #0008; }
        .ember-animation img { display: block; width: 100%; height: auto; }
        .ember-animation figcaption { padding: 0 18px 16px; font-size: 14px; }
        @keyframes reveal { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } } @keyframes drift { from { transform: translate3d(-20px, 8px, 0) scale(.9); } to { transform: translate3d(24px, -20px, 0) scale(1.08); } } @keyframes floatCover { 0%, 100% { transform: translateY(0) rotate(-1deg); } 50% { transform: translateY(-7px) rotate(1deg); } } @media(max-width:560px){.book-head{grid-template-columns:1fr}.cover{width:130px;height:190px}} @media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
<main class="{{ $book->slug === 'the-ember-nest' ? 'ember-book' : '' }}">
    <div class="book-head">
        @if ($book->slug !== 'the-ember-nest')
            @if ($book->cover_url)
                <img class="cover" src="{{ $book->cover_url }}" alt="Cover of {{ $book->title }}">
            @else
                <div class="cover"></div>
            @endif
        @endif
        <div>
            <div class="eyebrow">{{ $book->category === 'manga' ? 'Now reading manga' : 'Now reading' }}</div>
            <h1>{{ $book->title }}</h1>
            <div class="author">{{ $book->author }}</div>
            @if ($book->genre)
                <div class="author">{{ config('library.genres.' . $book->genre, $book->genre) }}</div>
            @endif
        </div>
    </div>
    @if ($book->slug === 'the-ember-nest')
        <figure class="ember-animation">
            <img src="{{ $book->cover_url }}" alt="Animated story scenes: Mara discovers a glowing stone, frees the dragon Orin, protects the egg in a storm, and welcomes a hatchling." fetchpriority="high">
            <figcaption>Watch Mara’s adventure unfold: the warm stone, the hidden nest, the mountain crossing, and a new beginning.</figcaption>
        </figure>
    @endif
    <p class="text">{{ $book->content }}</p>
    @if (is_array($book->illustrations) && count($book->illustrations))
        <section class="gallery" aria-label="Story illustrations">
            @foreach ($book->illustrations as $scene)
                <figure class="scene">
                    <img src="{{ $scene['url'] }}" alt="{{ $scene['caption'] ?? 'Story illustration' }}" loading="lazy">
                    <figcaption>{{ $scene['caption'] ?? 'Story illustration' }}</figcaption>
                </figure>
            @endforeach
        </section>
    @endif
    <div class="actions">
        <button id="download-book" type="button">Download book</button>
        <button id="share-book" type="button">Share book</button>
    </div>
    <p id="share-status" class="status" aria-live="polite"></p>
    @if ($book->source_url)
        <a href="{{ $book->source_url }}" target="_blank" rel="noopener">{{ $book->source_label ?: 'Open source' }}</a>
    @endif
    <a href="{{ route('reading.index') }}">Back to books</a>
</main>
<script>
const downloadButton = document.getElementById('download-book');
const shareButton = document.getElementById('share-book');
const shareStatus = document.getElementById('share-status');
downloadButton.addEventListener('click', async () => {
    const response = await fetch(@json(route('reading.download', $book->slug)));
    const file = await response.blob();
    const link = document.createElement('a');
    link.href = URL.createObjectURL(file);
    link.download = @json($book->slug . '.txt');
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
});
shareButton.addEventListener('click', async () => {
    const shareData = { title: @json($book->title), text: @json('Read ' . $book->title . ' in the Library Book Monitoring System.'), url: window.location.href };
    if (navigator.share) {
        await navigator.share(shareData);
        return;
    }
    await navigator.clipboard.writeText(window.location.href);
    shareStatus.textContent = 'Book link copied.';
});
</script>
<form method="POST" action="{{ route('reading.favorite', $book->slug) }}" style="margin-top:16px">
    @csrf
    <button type="submit">{{ \App\Models\BookActivity::where('user_id', auth()->id())->where('book_id', $book->id)->where('type', 'favorite')->exists() ? 'Remove favorite' : 'Add to favorites' }}</button>
</form>
</body>
</html>
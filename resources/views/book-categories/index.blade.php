<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Book Categories | Library Book Monitoring System</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:28px;font-family:Arial,sans-serif}main{max-width:900px;margin:auto;padding:24px;border:1px solid #31557f;border-radius:16px}.head,.category{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.head{margin-bottom:20px}.category{padding:14px 0;border-top:1px solid #31557f}.category form{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.category input,.create input{padding:10px;border:1px solid #31557f;border-radius:8px;background:#111d31;color:inherit;font:inherit}.create{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 24px}.create input{flex:1;min-width:220px}.category button,.create button,.head a{padding:10px 14px;border:0;border-radius:8px;background:#2f80ed;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}.category button.delete{background:#b83b4b}.muted{color:#aeb9d4}.error{color:#ff9ba6}.success{color:#9ed4a7}.pagination{margin-top:20px}
    </style>
</head>
<body>
<main>
    <div class="head">
        <div><p class="muted">Staff and admin workspace</p><h1>Book categories</h1><p class="muted">Manage genres shown in the catalog and book records.</p></div>
        <a href="{{ route('books.index') }}">Back to books</a>
    </div>
    @if (session('success'))<p class="success">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="error"><p>Please correct the following:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="create" method="POST" action="{{ route('book-categories.store') }}">
        @csrf
        <input name="name" value="{{ old('name') }}" maxlength="100" placeholder="New category name" aria-label="New category name" required>
        <button type="submit">Add category</button>
    </form>
    @forelse ($categories as $category)
        <article class="category">
            <div><strong>{{ $category->name }}</strong><div class="muted">{{ $category->books_count }} assigned book(s)</div></div>
            <form method="POST" action="{{ route('book-categories.update', $category->slug) }}">
                @csrf
                @method('PUT')
                <input name="name" value="{{ $category->name }}" maxlength="100" aria-label="Category name" required>
                <button type="submit">Save</button>
            </form>
            <form method="POST" action="{{ route('book-categories.destroy', $category->slug) }}" onsubmit="return confirm('Delete this category?')">
                @csrf
                @method('DELETE')
                <button class="delete" type="submit">Delete</button>
            </form>
        </article>
    @empty
        <p class="muted">No categories are available.</p>
    @endforelse
    <div class="pagination">{{ $categories->links() }}</div>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account</title>
    <style>
        :root { --navy: #0b1b2b; --blue: #1469f5; --ink: #172338; --muted: #7e8795; --line: #e7eaf0; --paper: #f4f6f9; }
        * { box-sizing: border-box; } body { margin: 0; min-height: 100vh; padding: 34px 18px; background: var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        main { max-width: 900px; margin: auto; padding: 30px; background: #fff; border: 1px solid var(--line); border-radius: 6px; box-shadow: 0 2px 12px #1b2d4510; }
        h1 { margin: 0; } p { color: var(--muted); } label { display: block; margin: 18px 0 7px; font-weight: 800; } input { width: 100%; padding: 12px; border: 1px solid #cfd5df; border-radius: 4px; font: inherit; } input:focus { outline: 3px solid #1469f533; border-color: var(--blue); }
        button, a { display: inline-block; margin-top: 22px; padding: 11px 15px; border: 0; border-radius: 4px; background: var(--blue); color: #fff; text-decoration: none; font: 800 13px 'Trebuchet MS', sans-serif; cursor: pointer; } a { margin-left: 8px; background: var(--navy); }
        .photo { display: flex; align-items: center; gap: 18px; margin: 24px 0 8px; } .photo img, .photo-placeholder { width: 78px; height: 78px; border-radius: 50%; object-fit: cover; } .photo-placeholder { display: grid; place-items: center; background: #e9e5ff; color: #6954be; font-size: 27px; font-weight: 900; } .photo input { padding: 8px; }
        .success, .error { padding: 11px 13px; border-radius: 4px; } .success { background: #e9f7ee; color: #176b3a; } .error { background: #fff0f1; color: #a52a38; } .history { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; } .history section { padding: 16px; background: #f8faff; border: 1px solid var(--line); } .history h2 { margin: 0 0 10px; font-size: 16px; } .history p { margin: 8px 0; font-size: 13px; } @media(max-width:650px) { .history { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <h1>My account</h1>
        <p>Update your name and profile picture. Role and password are managed by an administrator.</p>
        @if(session('success')) <p class="success">{{ session('success') }}</p> @endif
        @if($errors->any()) <div class="error">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('account.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="photo">
                @if($user->profile_photo_path)
                    <img src="{{ '/storage/' . ltrim($user->profile_photo_path, '/') }}" alt="Profile picture">
                @else
                    <span class="photo-placeholder">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                @endif
                <label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label>
            </div>
            <label for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @if($user->role === 'admin')
                <label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                <label for="password">New password <small>(leave blank to keep current)</small></label><input id="password" name="password" type="password" minlength="8">
                <label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8">
            @else
                <p><strong>Email:</strong> {{ $user->email }}<br><strong>Role:</strong> {{ ucfirst($user->role) }}</p>
            @endif
            <button type="submit">Save account</button><a href="{{ url('/' . $user->role . '/dashboard') }}">Back to dashboard</a>
        </form>
        <div class="history"><section><h2>Favorite books</h2>@forelse($favorites as $favorite)<p><a href="{{ route('reading.show', $favorite->book->slug) }}">{{ $favorite->book->title }}</a></p>@empty<p>No favorite books yet.</p>@endforelse</section><section><h2>Reading history</h2>@forelse($history as $item)<p><a href="{{ route('reading.show', $item->book->slug) }}">{{ $item->book->title }}</a></p>@empty<p>No reading history yet.</p>@endforelse</section></div>
    </main>
</body>
</html>
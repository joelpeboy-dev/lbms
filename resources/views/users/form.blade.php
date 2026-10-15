<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        :root { --blue: #102a72; --blue-bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --paper: #05070d; }
        * { box-sizing: border-box; } body { margin: 0; padding: 32px; background: var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; min-height: 100vh; }
        main { max-width: 540px; margin: auto; background: #0b1020ef; padding: 34px; border: 1px solid #3774ff66; border-radius: 20px; }
        h1 { margin: 0 0 20px; font-size: 34px; } label { display: block; margin: 16px 0 7px; font-weight: 800; } input, select { width: 100%; padding: 12px; border: 1px solid #3b4e7b; border-radius: 8px; font: inherit; color: var(--ink); background: #111a31; }
        button, a { display: inline-block; margin-top: 22px; padding: 11px 15px; border: 0; border-radius: 8px; background: var(--blue); color: #fff; text-decoration: none; font: 800 14px 'Trebuchet MS', sans-serif; cursor: pointer; } a { margin-left: 8px; } button:hover, a:hover { background: var(--blue-bright); }
        .error { padding: 11px; border-left: 4px solid var(--red); color: #ffb8c1; background: #3a1420; border-radius: 6px; } .current-photo { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; vertical-align: middle; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        @if($errors->any()) <div class="error">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf @if($method !== 'POST') @method($method) @endif
            <label for="name">Name</label><input id="name" name="name" value="{{ old('name', $user?->name) }}" required>
            <label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required>
            <label for="password">Password @if($user)<small>(leave blank to keep current)</small>@endif</label><input id="password" name="password" type="password" minlength="8" @required(!$user)>
            <label for="role">Role</label><select id="role" name="role" required>@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role', $user?->role) === $role)>{{ ucfirst($role) }}</option>@endforeach</select>
            <label for="profile_photo">Profile picture</label><input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
            @if($user?->profile_photo_path)<p>Current picture: <img class="current-photo" src="{{ '/storage/' . ltrim($user->profile_photo_path, '/') }}" alt="Current profile picture"></p>@endif
            <button type="submit">Save user</button><a href="{{ route('users.index') }}">Cancel</a>
        </form>
    </main>
</body>
</html>

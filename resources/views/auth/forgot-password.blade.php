<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password | Library Book Monitoring System</title>
    <style>
        :root { color-scheme: dark; --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #05070d; color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        main { width: min(100%, 460px); padding: 42px; background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 20px; box-shadow: 0 24px 70px #000, 0 0 40px #102a7240; }
        .mark { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 10px 0 8px; font-size: 34px; }
        p { color: var(--muted); line-height: 1.55; }
        label { display: block; margin: 18px 0 7px; font-size: 14px; font-weight: 800; }
        input { width: 100%; padding: 13px 14px; border: 1px solid #3b4e7b; border-radius: 8px; background: #111a31; color: var(--ink); font: inherit; }
        button { width: 100%; margin-top: 22px; padding: 14px; border: 0; border-radius: 9px; background: var(--blue); color: white; font: 800 15px 'Trebuchet MS', Verdana, sans-serif; cursor: pointer; }
        .message { margin: 14px 0; padding: 11px 13px; border-radius: 6px; background: #102a72; color: var(--ink); }
        .error { background: #461d2a; }
        a { color: #8dbaff; font-weight: 800; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <div class="mark">Library Book Monitoring System</div>
        <h1>Forgot your password?</h1>
        <p>Enter the email address linked to your account. If it matches an account, we’ll send a secure reset link.</p>

        @if (session('status'))
            <div class="message" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="message error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">Account email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            <button type="submit">Send reset link</button>
        </form>
        <p><a href="{{ route('login') }}">Back to login</a></p>
    </main>
</body>
</html>

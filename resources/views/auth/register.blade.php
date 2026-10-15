<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Library Book Monitoring System</title>
    <style>
        :root { color-scheme: dark; --blue: #102a72; --blue-bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; --paper: #05070d; font-family: 'Trebuchet MS', Verdana, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; overflow: hidden; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), radial-gradient(circle at 85% 80%, #e23d4f26, transparent 28%), var(--paper); color: var(--ink); }
        body::before { content: ''; position: fixed; z-index: -1; width: 380px; height: 380px; top: -180px; right: -80px; border-radius: 50%; background: #2556c729; animation: drift 9s ease-in-out infinite alternate; }
        main { width: min(100%, 460px); padding: 42px; background: #0b1020ef; border: 1px solid #3774ff66; border-radius: 20px; box-shadow: 0 24px 70px #000, 0 0 40px #102a7240; animation: reveal .65s ease both; }
        .mark { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 10px 0 8px; font-size: 38px; line-height: 1; }
        .intro { margin: 0 0 28px; color: var(--muted); line-height: 1.5; }
        label { display: block; margin: 18px 0 7px; font-size: 14px; font-weight: 800; }
        input { width: 100%; padding: 13px 14px; border: 1px solid #3b4e7b; border-radius: 8px; background: #111a31; color: var(--ink); font: inherit; }
        input:focus { outline: 3px solid #3774ff55; border-color: var(--blue-bright); }
        button { width: 100%; margin-top: 26px; padding: 14px; border: 0; border-radius: 9px; background: var(--blue); color: white; font: 800 15px inherit; cursor: pointer; transition: transform .2s, background .2s, box-shadow .2s; }
        button:hover { transform: translateY(-3px); background: var(--blue-bright); box-shadow: 0 10px 20px #102a7226; }
        .error { margin: 0 0 14px; padding: 11px 13px; border-left: 4px solid var(--red); border-radius: 6px; background: #fff0f2; color: #a22132; font-size: 14px; }
        a { color: var(--red); font-weight: 800; }
        @keyframes reveal { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes drift { from { transform: translate(0, 0); } to { transform: translate(18px, 14px); } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <div class="mark">Library Book Monitoring System</div>
        <h1>Create account</h1>
        <p class="intro">Register as a student to access the library system.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button type="submit">Register</button>
        </form>
        <p class="intro" style="margin: 22px 0 0; text-align: center;"><a href="{{ route('login') }}">Back to login</a></p>
    </main>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($role) }} dashboard</title>
    <style>
        :root { font-family: Georgia, 'Times New Roman', serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #e8efe9; color: #17342b; }
        main { width: min(100%, 600px); padding: 42px; background: #fffdf8; border: 1px solid #cad8ce; border-radius: 16px; box-shadow: 0 20px 50px #17342b1c; }
        h1 { margin: 8px 0 12px; font-size: 38px; }
        p { color: #5d7168; line-height: 1.5; }
        form { margin-top: 28px; }
        button { padding: 12px 18px; border: 0; border-radius: 8px; background: #17342b; color: #fffdf8; font: bold 14px Georgia, serif; cursor: pointer; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <p>Library Book Monitoring System</p>
        <h1>{{ ucfirst($role) }} dashboard</h1>
        <p>You are signed in as a {{ $role }}.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </main>
</body>
</html>
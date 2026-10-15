<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database backups</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif}main{max-width:900px;margin:auto;padding:24px;border:1px solid #31557f;border-radius:14px}.panel{margin-top:16px;padding:18px;border:1px solid #31557f;border-radius:12px;background:#111d31}.notice,.warning{padding:12px;border-radius:8px}.notice{background:#123526;color:#a8edc9}.warning{background:#402b14;color:#ffe0a0}button,.button{display:inline-block;padding:10px 14px;border:0;border-radius:8px;background:#2f80ed;color:white;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}input{display:block;max-width:100%;margin:10px 0;padding:10px}li{margin:12px 0}.muted{color:#aeb9d4}
    </style>
</head>
<body>
<main>
    <p class="muted">Administrator only · SQLite</p><h1>Database backup and restore</h1>
    <p>Backups are stored privately outside the public web directory. Restore accepts only intact backups from this application's current schema version.</p>
    @if(session('success'))<p class="notice">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="warning">{{ $errors->first() }}</p>@endif
    <section class="panel"><h2>Create a backup</h2><p class="muted">A consistent SQLite snapshot is written to private application storage.</p><form method="POST" action="{{ route('admin.backups.create') }}">@csrf<button type="submit">Create backup now</button></form></section>
    <section class="panel"><h2>Restore a backup</h2><p class="warning">Restoring replaces the active database. A safety backup of the current database is automatically created first. Confirm you are uploading the intended backup.</p><form method="POST" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data">@csrf<label for="backup">SQLite backup file</label><input id="backup" type="file" name="backup" accept=".sqlite,.db,application/vnd.sqlite3" required><button type="submit">Validate and restore</button></form></section>
    <section class="panel"><h2>Saved private backups</h2><ul>@forelse($files as $file)<li><strong>{{ $file['name'] }}</strong> — {{ number_format($file['size'] / 1024, 1) }} KB · {{ \Illuminate\Support\Carbon::createFromTimestamp($file['updated_at'])->format('M j, Y g:i A') }}<br><a class="button" href="{{ route('admin.backups.download', ['file' => $file['name']]) }}">Download</a></li>@empty<li>No backups created yet.</li>@endforelse</ul></section>
    <a class="button" href="{{ url('/admin/dashboard') }}">Dashboard</a>
</main>
</body>
</html>

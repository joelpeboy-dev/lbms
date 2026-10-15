<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Attendance</title>
    <style>
        :root { --navy: #0b1b2b; --blue: #1469f5; --ink: #172338; --muted: #7e8795; --line: #e7eaf0; --paper: #f4f6f9; } * { box-sizing: border-box; } body { margin: 0; padding: 34px 18px; background: var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; } main { max-width: 1100px; margin: auto; padding: 30px; background: #fff; border-radius: 6px; box-shadow: 0 2px 12px #1b2d4510; } h1 { margin: 0; } p { color: var(--muted); } .toolbar { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin: 24px 0; padding: 18px; background: #f8faff; border: 1px solid var(--line); } label { display: grid; gap: 7px; font-weight: 800; } select { min-width: 260px; padding: 11px; border: 1px solid #cfd5df; border-radius: 4px; font: inherit; } button, a { padding: 11px 14px; border: 0; border-radius: 4px; background: var(--blue); color: #fff; text-decoration: none; font: 800 13px 'Trebuchet MS', sans-serif; cursor: pointer; } button.out { background: #d95c31; } a { background: var(--navy); } table { width: 100%; border-collapse: collapse; } th, td { padding: 13px 10px; border-bottom: 1px solid var(--line); text-align: left; } th { color: var(--muted); font-size: 12px; text-transform: uppercase; } .success, .error { padding: 11px 13px; border-radius: 4px; } .success { background: #e9f7ee; color: #176b3a; } .error { background: #fff0f1; color: #a52a38; } @media(max-width:700px) { main { padding: 20px; overflow-x: auto; } table { min-width: 700px; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <h1>Staff and student attendance</h1>
        <p>Record who is in or out and review the latest attendance history.</p>
        @if(session('success')) <p class="success">{{ session('success') }}</p> @endif
        @if($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
        <form class="toolbar" method="POST" action="{{ route('attendance.store') }}">
            @csrf
            <label for="user_id">Staff or student<select id="user_id" name="user_id" required><option value="">Choose a person</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ ucfirst($user->role) }}: {{ $user->name }}</option>@endforeach</select></label>
            <button name="action" value="in" type="submit">Clock in</button><button class="out" name="action" value="out" type="submit">Clock out</button><a href="{{ url('/admin/dashboard') }}">Dashboard</a>
        </form>
        <table><thead><tr><th>Person</th><th>Role</th><th>Time in</th><th>Time out</th><th>Recorded by</th></tr></thead><tbody>
            @forelse($attendances as $attendance)<tr><td>{{ $attendance->user->name }}</td><td>{{ ucfirst($attendance->user->role) }}</td><td>{{ $attendance->time_in->format('M j, Y g:i A') }}</td><td>{{ $attendance->time_out?->format('M j, Y g:i A') ?? 'Still in' }}</td><td>{{ $attendance->recorder->name }}</td></tr>@empty<tr><td colspan="5">No attendance records yet.</td></tr>@endforelse
        </tbody></table>
    </main>
</body>
</html>
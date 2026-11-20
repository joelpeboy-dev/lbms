<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My borrowing | Library Book Monitoring System</title>
    <style>
        :root { --bg: #070b14; --panel: #101a2b; --line: #293a55; --ink: #edf4ff; --muted: #aeb9d4; --blue: #3774ff; --red: #ff929c; --green: #80d7a0; }
        * { box-sizing: border-box; } body { margin: 0; padding: 30px; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), var(--bg); color: var(--ink); font: 15px 'Trebuchet MS', Verdana, sans-serif; } main { width: min(1000px, 100%); margin: auto; } header { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 22px; } h1 { margin: 0; font-size: clamp(30px, 5vw, 46px); } p { color: var(--muted); } a { padding: 10px 14px; border-radius: 8px; background: var(--blue); color: white; text-decoration: none; font-weight: 800; } section { overflow-x: auto; padding: 22px; border: 1px solid var(--line); border-radius: 14px; background: var(--panel); } table { width: 100%; border-collapse: collapse; } th, td { min-width: 130px; padding: 13px 10px; border-bottom: 1px solid var(--line); text-align: left; } th { color: var(--muted); font-size: 12px; text-transform: uppercase; } .overdue { color: var(--red); font-weight: 800; } .returned { color: var(--green); font-weight: 800; } .borrowed { color: #b7d5ff; font-weight: 800; } @media(max-width:650px) { body { padding: 16px; } header { align-items: start; flex-direction: column; } section { padding: 12px; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
<main>
    <header><div><p>Library account</p><h1>My borrowing</h1></div><a href="{{ route('reading.index') }}">Browse library</a></header>
    <section><table>
        <thead><tr><th>Title</th><th>Issued</th><th>Due date</th><th>Returned</th><th>Fine</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($loans as $loan)
            @php($status = $loan->voided_at ? 'Voided' : ($loan->returned_at ? 'Returned' : ($loan->isOverdue() ? 'Overdue' : 'Borrowed')))
            <tr>
                <td>{{ $loan->book->title }}</td>
                <td>{{ $loan->borrowed_at->format('M j, Y') }}</td>
                <td>{{ $loan->due_at->format('M j, Y') }}</td>
                <td>{{ $loan->returned_at?->format('M j, Y') ?? '—' }}</td>
                <td>@if ((float) $loan->fine_amount > 0) ${{ number_format((float) $loan->fine_amount, 2) }} @else — @endif</td>
                <td class="{{ strtolower($status) }}">{{ $status }}</td>
            </tr>
        @empty
            <tr><td colspan="6">You do not have any borrowing records yet.</td></tr>
        @endforelse
        </tbody>
    </table>{{ $loans->links() }}</section>
</main>
</body>
</html>

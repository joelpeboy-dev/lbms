<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My reservations | Library Book Monitoring System</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body { margin: 0; min-height: 100vh; padding: 32px; font-family: 'Segoe UI', Arial, sans-serif; }
        main { width: min(1000px, 100%); margin: auto; padding: 30px; border: 1px solid #2a3e5a; border-radius: 18px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(28px, 5vw, 42px); }
        .eyebrow { margin: 0 0 7px; color: #8dbaff; font-size: 12px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .panel { overflow-x: auto; padding: 18px; border: 1px solid #2a3e5a; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 10px; border-bottom: 1px solid #2a3e5a; text-align: left; vertical-align: top; }
        th { color: #a6b6cb; font-size: 12px; text-transform: uppercase; }
        button, .back-link { padding: 9px 12px; border: 0; border-radius: 9px; background: #3478f6; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .cancel { background: #dc5262; }
        .notice, .error { padding: 12px 14px; border-radius: 9px; }
        .notice { background: #123526; color: #a8edc9; }
        .error { background: #3a2028; color: #ffc0c7; }
        .status { font-weight: 700; text-transform: capitalize; }
        @media (max-width: 700px) { body { padding: 14px; } main { padding: 18px; } table { min-width: 650px; } }
    </style>
</head>
<body>
<main>
    <header>
        <div><p class="eyebrow">Library account</p><h1>My reservations</h1><p>Review your reservation queue and issued books.</p></div>
        <a class="back-link" href="{{ route('reading.index') }}">Browse books</a>
    </header>
    @if (session('success')) <p class="notice">{{ session('success') }}</p> @endif
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <section class="panel">
        <table>
            <thead><tr><th>Book</th><th>Requested</th><th>Status</th><th>Loan due date</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($reservations as $reservation)
                <tr>
                    <td><strong>{{ $reservation->book->title }}</strong><br><span>{{ $reservation->book->author }}</span></td>
                    <td>{{ $reservation->requested_at->format('M j, Y') }}</td>
                    <td class="status">{{ $reservation->status }}</td>
                    <td>{{ $reservation->loan?->due_at?->format('M j, Y') ?? '—' }}</td>
                    <td>
                        @if ($reservation->status === 'pending')
                            <form method="POST" action="{{ route('reservations.cancel', $reservation) }}">
                                @csrf
                                <button class="cancel" type="submit">Cancel reservation</button>
                            </form>
                        @elseif ($reservation->status === 'fulfilled')
                            Issued by {{ $reservation->fulfiller?->name ?? 'library staff' }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">You have not made any reservations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $reservations->links() }}
    </section>
</main>
</body>
</html>

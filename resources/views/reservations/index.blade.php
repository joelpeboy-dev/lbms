<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book reservations | Library Book Monitoring System</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body { margin: 0; min-height: 100vh; padding: 32px; font-family: 'Segoe UI', Arial, sans-serif; }
        main { width: min(1120px, 100%); margin: auto; padding: 30px; border: 1px solid #2a3e5a; border-radius: 18px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(28px, 5vw, 42px); }
        .eyebrow { margin: 0 0 7px; color: #8dbaff; font-size: 12px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .panel { overflow-x: auto; padding: 18px; border: 1px solid #2a3e5a; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 10px; border-bottom: 1px solid #2a3e5a; text-align: left; vertical-align: top; }
        th { color: #a6b6cb; font-size: 12px; text-transform: uppercase; }
        .muted { color: #a6b6cb; }
        .fulfill-form { display: grid; gap: 8px; min-width: 190px; }
        .fulfill-form label { display: grid; gap: 6px; font-size: 12px; }
        .fulfill-form button, .back-link { padding: 10px 13px; border: 0; border-radius: 9px; background: #3478f6; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .back-link { display: inline-block; }
        .notice, .error { padding: 12px 14px; border-radius: 9px; }
        .notice { background: #123526; color: #a8edc9; }
        .error { background: #3a2028; color: #ffc0c7; }
        @media (max-width: 700px) { body { padding: 14px; } main { padding: 18px; } table { min-width: 760px; } }
    </style>
</head>
<body>
<main>
    <header>
        <div><p class="eyebrow">Staff and admin workspace</p><h1>Book reservations</h1><p>Issue returned books in reservation order.</p></div>
        <a class="back-link" href="{{ url('/' . auth()->user()->role . '/dashboard') }}">Dashboard</a>
    </header>
    @if (session('success')) <p class="notice">{{ session('success') }}</p> @endif
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <section class="panel">
        <table>
            <thead><tr><th>Queue</th><th>Book</th><th>Student</th><th>Requested</th><th>Availability</th><th>Issue</th></tr></thead>
            <tbody>
            @forelse ($reservations as $reservation)
                @php($available = $reservation->book->condition_status === 'available' ? max(0, $reservation->book->total_copies - $reservation->book->active_loans_count) : 0)
                <tr>
                    <td>#{{ $reservations->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $reservation->book->title }}</strong><br><span class="muted">by {{ $reservation->book->author }}</span></td>
                    <td>{{ $reservation->student->name }}<br><span class="muted">{{ $reservation->student->email }}</span></td>
                    <td>{{ $reservation->requested_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $available }} available</td>
                    <td>
                        @if ($available > 0 && $nextReservationByBook->get($reservation->book_id)?->id === $reservation->id)
                            <form class="fulfill-form" method="POST" action="{{ route('reservations.fulfill', $reservation) }}">
                                @csrf
                                <label>Due date<input type="date" name="due_at" value="{{ now()->addDays(14)->toDateString() }}" min="{{ now()->toDateString() }}" required></label>
                                <button type="submit">Issue reserved book</button>
                            </form>
                        @elseif ($available < 1)
                            <span class="muted">Waiting for a return</span>
                        @else
                            <span class="muted">Waiting for earlier reservation</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">There are no pending reservations.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $reservations->links() }}
    </section>
</main>
</body>
</html>

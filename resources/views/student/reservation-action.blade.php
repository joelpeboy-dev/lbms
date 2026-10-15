@if (auth()->user()->role === 'student' && $available === 0)
    @if ($book->has_pending_reservation)
        <a href="{{ route('reservations.mine') }}">Reservation pending</a>
    @else
        <form method="POST" action="{{ route('reservations.store', $book->slug) }}">
            @csrf
            <button type="submit">Reserve this book</button>
        </form>
    @endif
@endif

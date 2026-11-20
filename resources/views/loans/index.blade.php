<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing | Library Book Monitoring System</title>
    <style>
        :root { --bg: #070b14; --panel: #101a2b; --line: #293a55; --ink: #edf4ff; --muted: #aeb9d4; --blue: #3774ff; --red: #e23d4f; --green: #80d7a0; }
        * { box-sizing: border-box; } body { margin: 0; padding: 30px; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), var(--bg); color: var(--ink); font: 15px 'Trebuchet MS', Verdana, sans-serif; } main { width: min(1180px, 100%); margin: auto; }
        header, .toolbar, .loan-form { display: flex; align-items: center; justify-content: space-between; gap: 14px; } header { margin-bottom: 22px; } h1 { margin: 0; font-size: clamp(30px, 5vw, 46px); } h2 { margin-top: 0; } p { color: var(--muted); } a, button { padding: 10px 14px; border: 0; border-radius: 8px; background: var(--blue); color: white; text-decoration: none; font: inherit; font-weight: 800; cursor: pointer; } .panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 14px; background: var(--panel); } .notice { color: var(--green); } .error { color: #ff9ba6; } label { display: block; color: var(--muted); font-size: 13px; } input, select { min-width: 0; padding: 10px; border: 1px solid var(--line); border-radius: 7px; background: #080f1b; color: var(--ink); font: inherit; } .loan-form { justify-content: start; flex-wrap: wrap; align-items: end; } .loan-form label { flex: 1 1 180px; } .loan-form input, .loan-form select { display: block; width: 100%; margin-top: 7px; } .toolbar { justify-content: start; flex-wrap: wrap; margin-bottom: 16px; } table { width: 100%; border-collapse: collapse; } th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; } th { color: var(--muted); font-size: 12px; text-transform: uppercase; } .status { font-weight: 800; } .overdue { color: #ff929c; } .returned { color: var(--green); } .table-wrap { overflow-x: auto; } .inline { display: inline; } .return { padding: 7px 10px; font-size: 12px; background: #14804a; } @media(max-width: 650px) { body { padding: 16px; } header { align-items: start; flex-direction: column; } .panel { padding: 14px; } th, td { min-width: 125px; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
<main>
    <header><div><p>Staff and admin workspace</p><h1>Borrowing and returns</h1></div><div><a href="{{ route('books.index') }}">Manage books</a> <a href="{{ url('/' . auth()->user()->role . '/dashboard') }}">Dashboard</a></div></header>
    @if (session('success')) <p class="notice">{{ session('success') }}</p> @endif
    @if ($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <section class="panel">
        <h2>Issue a book</h2>
        <form class="loan-form" method="POST" action="{{ route('loans.store') }}">
            @csrf
            <label>Scan a barcode<input id="loan-barcode" type="search" autocomplete="off" placeholder="Scan barcode, then press Enter"></label>
            <label>Book<select id="loan-book" name="book_id" required><option value="">Select an available title</option>@foreach ($books as $book) @if ($book->condition_status === 'available' && $book->total_copies > $book->active_loans_count)<option value="{{ $book->id }}" data-barcode="{{ $book->barcode }}" @selected(old('book_id') == $book->id)>{{ $book->title }} ({{ $book->total_copies - $book->active_loans_count }} available)</option>@endif @endforeach</select></label>
            <label>Student<select name="borrower_id" required><option value="">Select a student</option>@foreach ($borrowers as $borrower)<option value="{{ $borrower->id }}" @selected(old('borrower_id') == $borrower->id)>{{ $borrower->name }}</option>@endforeach</select></label>
            <label>Due date<input type="date" name="due_at" value="{{ old('due_at', now()->addDays(14)->toDateString()) }}" min="{{ now()->toDateString() }}" required></label>
            <button type="submit">Issue book</button>
        </form>
    </section>
    <section class="panel">
        <h2>Loan records</h2>
        <form class="toolbar" method="GET" action="{{ route('loans.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search borrower or title" aria-label="Search borrower or title">
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach (['borrowed' => 'Borrowed', 'overdue' => 'Overdue', 'returned' => 'Returned', 'voided' => 'Voided'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button type="submit">Filter</button>
        </form>
        <div class="table-wrap"><table>
            <thead><tr><th>Title / borrower</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($loans as $loan)
                @php($status = $loan->voided_at ? 'Voided' : ($loan->returned_at ? 'Returned' : ($loan->isOverdue() ? 'Overdue' : 'Borrowed')))
                @php($fineLabel = (float) $loan->fine_amount > 0 ? '$' . number_format((float) $loan->fine_amount, 2) : '—')
                <tr>
                    <td><strong>{{ $loan->book->title }}</strong><br>{{ $loan->borrower->name }}</td>
                    <td>{{ $loan->borrowed_at->format('M j, Y') }}<br><span class="muted">by {{ $loan->issuer?->name ?? 'Former staff' }}</span></td>
                    <td>{{ $loan->due_at->format('M j, Y') }}</td>
                    <td>{{ $loan->returned_at?->format('M j, Y') ?? '—' }}</td>
                    <td>@if ((float) $loan->fine_amount > 0) <span>{{ $fineLabel }}@if ($loan->fine_paid_at === null) <br><strong>Unpaid</strong>@else <br><strong>Paid</strong>@endif</span> @else <span>—</span> @endif</td>
                    <td class="status {{ strtolower($status) }}">{{ $status }}</td>
                    <td>@if (! $loan->returned_at && ! $loan->voided_at)<form class="inline" method="POST" action="{{ route('loans.return', $loan) }}">@csrf<button class="return" type="submit">Record return</button></form>@if ((float) $loan->fine_amount > 0 && $loan->fine_paid_at === null)<form class="inline" method="POST" action="{{ route('loans.fine.pay', $loan) }}">@csrf<button class="return" type="submit" style="background:#d4a017;">Mark fine paid</button></form>@endif@elseif ($loan->voided_at)<span class="muted">voided by {{ $loan->voider?->name ?? 'Former staff' }}</span>@else<span class="muted">by {{ $loan->returner?->name ?? 'Former staff' }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7">No loan records match your filters.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $loans->links() }}
    </section>
</main>
<script>
const barcodeInput = document.getElementById('loan-barcode');
const bookSelect = document.getElementById('loan-book');
barcodeInput.addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const scanned = barcodeInput.value.trim().toLowerCase();
    const match = Array.from(bookSelect.options).find(option => option.dataset.barcode?.toLowerCase() === scanned);
    if (!match) {
        barcodeInput.setCustomValidity('No available title matches that barcode.');
        barcodeInput.reportValidity();
        barcodeInput.setCustomValidity('');
        return;
    }
    bookSelect.value = match.value;
    bookSelect.focus();
});
</script>
</body>
</html>

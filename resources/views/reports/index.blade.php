<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library reports</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:28px;font-family:Arial,sans-serif}main{max-width:1100px;margin:auto;padding:26px;border:1px solid #31557f;border-radius:16px}.head,.actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:22px 0}.card,section{padding:16px;border:1px solid #31557f;border-radius:12px;background:#111d31}.card strong{display:block;font-size:25px}.card span,small{color:#aeb9d4}.actions a,button{padding:10px 14px;border:0;border-radius:8px;background:#2f80ed;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}.tables{display:grid;grid-template-columns:1fr 1fr;gap:14px}table{width:100%;border-collapse:collapse}td,th{padding:9px;border-bottom:1px solid #31557f;text-align:left}@media(max-width:700px){body{padding:12px}.tables{grid-template-columns:1fr}}@media print{body{padding:0;background:white;color:#111}main{border:0}.actions{display:none}.card,section{background:white;color:#111;border-color:#bbb}}
    </style>
</head>
<body>
<main>
    <div class="head">
        <div><p>Staff and admin workspace</p><h1>Library reports</h1><p>Current inventory, borrowing and circulation at a glance.</p></div>
        <div class="actions"><a href="{{ route('reports.books') }}">Export books CSV</a><a href="{{ route('reports.loans') }}">Export loans CSV</a><button type="button" onclick="window.print()">Print report</button><a href="{{ url('/'.auth()->user()->role.'/dashboard') }}">Dashboard</a></div>
    </div>
    <section class="cards" aria-label="Library summary">
        <div class="card"><strong>{{ $metrics['titles'] }}</strong><span>Titles</span></div>
        <div class="card"><strong>{{ $metrics['copies'] }}</strong><span>Total copies</span></div>
        <div class="card"><strong>{{ $metrics['available_copies'] }}</strong><span>Available copies</span></div>
        <div class="card"><strong>{{ $metrics['active_loans'] }}</strong><span>Active loans</span></div>
        <div class="card"><strong>{{ $metrics['overdue_loans'] }}</strong><span>Overdue loans</span></div>
        <div class="card"><strong>{{ $metrics['lost_titles'] }}</strong><span>Titles marked lost</span></div>
    </section>
    <div class="tables">
        <section><h2>Most borrowed titles</h2><table><thead><tr><th>Book</th><th>Loans</th></tr></thead><tbody>@forelse($popularBooks as $book)<tr><td>{{ $book->title }}</td><td>{{ $book->loans_count }}</td></tr>@empty<tr><td colspan="2">No loans recorded.</td></tr>@endforelse</tbody></table></section>
        <section><h2>Recent borrowing</h2><table><thead><tr><th>Book</th><th>Borrower</th><th>Due</th></tr></thead><tbody>@forelse($recentLoans as $loan)<tr><td>{{ $loan->book->title }}</td><td>{{ $loan->borrower->name }}</td><td>{{ $loan->due_at->format('M j, Y') }}</td></tr>@empty<tr><td colspan="3">No loans recorded.</td></tr>@endforelse</tbody></table></section>
    </div>
</main>
</body>
</html>

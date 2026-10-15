<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library activity log</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif}main{max-width:1150px;margin:auto;padding:22px;border:1px solid #31557f;border-radius:14px}.table-wrap{overflow:auto;margin-top:20px}table{width:100%;border-collapse:collapse;min-width:800px}td,th{padding:12px;border-bottom:1px solid #31557f;text-align:left;vertical-align:top}th,.muted{color:#aeb9d4}code{white-space:pre-wrap;overflow-wrap:anywhere}.button{display:inline-block;margin:6px 0;padding:9px 13px;border-radius:8px;background:#2f80ed;color:white;text-decoration:none;font-weight:700}
    </style>
</head>
<body>
<main>
    <p class="muted">Administrator only</p><h1>Library activity log</h1><p class="muted">Sensitive catalog and circulation actions are recorded with the responsible account and timestamp.</p>
    <a class="button" href="{{ url('/admin/dashboard') }}">Dashboard</a>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
    @forelse($logs as $log)
        <tr><td>{{ $log->created_at->format('M j, Y g:i:s A') }}</td><td>{{ $log->user?->name ?? 'Deleted account' }}</td><td>{{ $log->action }}</td><td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : 'System' }}</td><td><code>{{ json_encode($log->details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></td></tr>
    @empty<tr><td colspan="5">No recorded actions yet.</td></tr>@endforelse
    </tbody></table></div>{{ $logs->links() }}
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change login image | Library Book Monitoring System</title>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
    <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif}main{max-width:760px;margin:auto;padding:26px;border:1px solid #31557f;border-radius:14px;background:#111d31}.muted{color:#aeb9d4}.preview{display:block;width:100%;height:190px;margin:18px 0;object-fit:cover;object-position:center 58%;border:1px solid #31557f;border-radius:10px;background:#0b1b2b}label{display:block;margin:16px 0 8px;font-weight:700}input[type=file]{display:block;width:100%;margin:10px 0;padding:10px;border:1px solid #31557f;border-radius:8px}button,.link{display:inline-block;margin:8px 8px 0 0;padding:10px 15px;border:0;border-radius:8px;background:#2f80ed;color:white;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.secondary{background:#334155}.success,.error,.hint{padding:12px;border-radius:8px}.success{background:#123526;color:#a8edc9}.error{background:#40242a;color:#ffc1c8}.hint{background:#182b45;color:#b9dcff}
    </style>
</head>
<body>
<main>
    <p class="muted">Administrator only</p>
    <h1>Login page image</h1>
    <p class="muted">Choose the wide picture displayed above the login form. Only administrators can update it.</p>
    @if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif

    <img class="preview" src="{{ $setting?->image_path ? Storage::disk('public')->url($setting->image_path) : $defaultImage }}" alt="Current login page image preview">
    <p class="hint">Accepted formats: JPG, PNG, or WebP. Maximum file size is 5 MB; maximum dimensions are 2400 × 1600 pixels.</p>

    <form method="POST" action="{{ route('admin.login-branding.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <label for="login-image">Upload a new login picture</label>
        <input id="login-image" type="file" name="login_image" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit">Save login picture</button>
    </form>

    @if($setting?->image_path)
        <form method="POST" action="{{ route('admin.login-branding.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="remove_image" value="1">
            <button class="secondary" type="submit">Restore default picture</button>
        </form>
    @endif

    <a class="link secondary" href="{{ url('/admin/dashboard') }}">Back to dashboard</a>
</main>
</body>
</html>

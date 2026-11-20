<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BookActivityController;
use App\Http\Controllers\BookCategoryController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookLoanController;
use App\Http\Controllers\BookReservationController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LibraryFeaturesController;
use App\Http\Controllers\LoginBrandingController;
use App\Http\Controllers\StudentStudyHelperController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\StaffOrAdmin;
use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookActivity;
use App\Models\BookCategory;
use App\Models\BookLoan;
use App\Models\LoginPageSetting;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    $imagePath = LoginPageSetting::query()->find(LoginPageSetting::SINGLETON_ID)?->image_path;

    return view()->file(resource_path('views/log.in.php'), [
        'loginImageUrl' => $imagePath
            ? Storage::disk('public')->url($imagePath)
            : asset('images/library-login-art.svg'),
    ]);
})->name('login');

Route::redirect('/log in', '/login');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

Route::post('/forgot-password', function () {
    request()->validate([
        'email' => ['required', 'email', 'max:255'],
    ]);

    Password::sendResetLink(request()->only('email'));

    return back()->with('status', 'If an account matches that email, a password reset link has been sent.');
})->middleware('throttle:6,1')->name('password.email');

Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => request()->query('email'),
    ]);
})->name('password.reset');

Route::post('/reset-password', function () {
    $data = request()->validate([
        'token' => ['required', 'string'],
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'confirmed', PasswordRule::min(8)],
    ]);

    $status = Password::reset(
        $data,
        function (User $user, string $password): void {
            $user->tokens()->delete();
            $user->apiTokens()->delete();
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        },
    );

    if ($status !== Password::PASSWORD_RESET) {
        return back()->withErrors([
            'email' => 'This password reset link is invalid or expired. Request a new one.',
        ]);
    }

    return redirect()->route('login')->with('status', 'Your password has been reset. You can now log in.');
})->middleware('throttle:6,1')->name('password.update');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::post('/register', function () {
    $data = request()->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', 'string', 'min:8'],
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => $data['password'],
        'role' => 'student',
    ]);

    Auth::login($user);
    Attendance::clockIn($user);
    request()->session()->regenerate();
    request()->session()->put('welcome_animation_user_id', $user->id);

    return redirect('/student/dashboard');
})->name('register.store');

Route::post('/login', function () {
    $credentials = request()->validate([
        'name' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials, request()->boolean('remember'))) {
        return back()->withErrors([
            'name' => 'The name or password is incorrect.',
        ])->withInput(request()->only('name'));
    }

    request()->session()->regenerate();
    Attendance::clockIn(auth()->user());
    request()->session()->put('welcome_animation_user_id', auth()->id());

    return redirect()->intended('/'.auth()->user()->role.'/dashboard');
})->middleware('throttle:6,1')->name('login.store');

Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', function (WeatherService $weatherService) {
        abort_unless(auth()->user()->role === 'admin', 403);

        $query = request()->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'city_search' => ['nullable', 'string', 'max:100'],
        ]);
        $city = trim($query['city'] ?? '') ?: trim($query['city_search'] ?? '');

        return view('dashboards.admin', [
            'userCount' => User::count(),
            'bookCount' => Book::count(),
            'studyCount' => Book::where('category', 'study')->count(),
            'weather' => $weatherService->current(is_string($city) ? $city : null),
            'weatherCity' => is_string($city) ? $city : '',
            'weatherCities' => config('services.weather.cities', []),
            'welcomeAnimation' => ($welcomeAnimationUserId = request()->session()->pull('welcome_animation_user_id')) !== null
                && (int) $welcomeAnimationUserId === (int) auth()->id(),
            'activeLoanCount' => BookLoan::query()->whereNull('returned_at')->count(),
            'overdueLoanCount' => BookLoan::query()->whereNull('returned_at')->where('due_at', '<', now())->count(),
        ]);
    })->name('admin.dashboard');

    Route::get('/staff/dashboard', function (WeatherService $weatherService) {
        abort_unless(auth()->user()->role === 'staff', 403);

        $query = request()->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'city_search' => ['nullable', 'string', 'max:100'],
        ]);
        $city = trim($query['city'] ?? '') ?: trim($query['city_search'] ?? '');

        return view('dashboards.staff', [
            'userCount' => User::count(),
            'bookCount' => Book::count(),
            'studyCount' => Book::where('category', 'study')->count(),
            'weather' => $weatherService->current(is_string($city) ? $city : null),
            'weatherCity' => is_string($city) ? $city : '',
            'weatherCities' => config('services.weather.cities', []),
            'welcomeAnimation' => ($welcomeAnimationUserId = request()->session()->pull('welcome_animation_user_id')) !== null
                && (int) $welcomeAnimationUserId === (int) auth()->id(),
            'activeLoanCount' => BookLoan::query()->whereNull('returned_at')->count(),
            'overdueLoanCount' => BookLoan::query()->whereNull('returned_at')->where('due_at', '<', now())->count(),
        ]);
    });

    Route::get('/student/dashboard', function (WeatherService $weatherService) {
        abort_unless(auth()->user()->role === 'student', 403);

        $query = request()->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'city_search' => ['nullable', 'string', 'max:100'],
        ]);
        $city = trim($query['city'] ?? '') ?: trim($query['city_search'] ?? '');

        return view('dashboards.student', [
            'userCount' => User::count(),
            'bookCount' => Book::count(),
            'studyCount' => Book::where('category', 'study')->count(),
            'weather' => $weatherService->current(is_string($city) ? $city : null),
            'weatherCity' => is_string($city) ? $city : '',
            'weatherCities' => config('services.weather.cities', []),
            'welcomeAnimation' => ($welcomeAnimationUserId = request()->session()->pull('welcome_animation_user_id')) !== null
                && (int) $welcomeAnimationUserId === (int) auth()->id(),
            'activeLoanCount' => auth()->user()->bookLoans()->whereNull('returned_at')->count(),
            'overdueLoanCount' => auth()->user()->bookLoans()->whereNull('returned_at')->where('due_at', '<', now())->count(),
        ]);
    });

    Route::get('/my-loans', [BookLoanController::class, 'mine'])->name('loans.mine');
    Route::get('/my-reading', [LibraryFeaturesController::class, 'readingHistory'])->name('reading.history');
    Route::get('/my-reservations', [BookReservationController::class, 'mine'])->name('reservations.mine');
    Route::get('/reservations', [BookReservationController::class, 'index'])
        ->middleware(StaffOrAdmin::class)->name('reservations.index');
    Route::post('/reading/{book:slug}/reservations', [BookReservationController::class, 'store'])
        ->name('reservations.store');
    Route::post('/my-reservations/{reservation}/cancel', [BookReservationController::class, 'cancel'])
        ->name('reservations.cancel');
    Route::post('/reservations/{reservation}/fulfill', [BookReservationController::class, 'fulfill'])
        ->middleware(StaffOrAdmin::class)->name('reservations.fulfill');

    Route::get('/student/study', function () {
        abort_unless(auth()->user()->role === 'student', 403);

        return view('student.study');
    })->name('study.index');

    Route::get('/student/study-helper', [StudentStudyHelperController::class, 'show'])->name('study.helper');
    Route::post('/student/study-helper', [StudentStudyHelperController::class, 'answer'])
        ->middleware('throttle:10,1')
        ->name('study.helper.answer');

    Route::get('/reading', function () {
        $filters = request()->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:book,study,manga'],
            'genre' => ['nullable', Rule::exists('book_categories', 'slug')],
        ]);
        $search = $filters['search'] ?? null;
        $category = $filters['category'] ?? null;
        $genre = $filters['genre'] ?? null;
        $catalog = fn (string $section) => Book::query()
            ->withCount(['loans as active_loans_count' => fn ($query) => $query->whereNull('returned_at')])
            ->withExists(['reservations as has_pending_reservation' => fn ($query) => $query
                ->where('student_id', auth()->id())
                ->where('status', 'pending')])
            ->where('category', $section)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($genre, fn ($query) => $query->where('genre', $genre))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")
                ->orWhere('genre', 'like', "%{$search}%")
                ->orWhere('barcode', $search)
                ->orWhere('description', 'like', "%{$search}%")))
            ->orderBy('title')
            ->get();

        return view('student.reading', [
            'books' => $catalog('book'),
            'study' => $catalog('study'),
            'manga' => $catalog('manga'),
            'filters' => $filters,
            'genres' => BookCategory::query()->orderBy('name')->pluck('name', 'slug')->all(),
        ]);
    })->name('reading.index');

    Route::get('/reading/{book:slug}', function (Book $book) {
        if (in_array(auth()->user()->role, ['staff', 'student'], true)) {
            BookActivity::create([
                'user_id' => auth()->id(),
                'book_id' => $book->id,
                'type' => 'viewed',
            ]);
        }

        return view('student.book', [
            'book' => $book,
            'genres' => BookCategory::query()->pluck('name', 'slug')->all(),
        ]);
    })->name('reading.show');

    Route::get('/reading/{book:slug}/download', function (Book $book) {
        $download = $book->title."\nBy ".$book->author."\n\n".$book->content;

        return response($download, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$book->slug.'.txt"',
        ]);
    })->name('reading.download');

    Route::post('/reading/{book:slug}/favorite', [BookActivityController::class, 'favorite'])->name('reading.favorite');

    Route::middleware(StaffOrAdmin::class)->prefix('books')->name('books.')->controller(BookController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{book}/edit', 'edit')->name('edit');
        Route::put('/{book}', 'update')->name('update');
        Route::delete('/{book}', 'destroy')->name('destroy');
    });

    Route::middleware(StaffOrAdmin::class)->prefix('book-categories')->name('book-categories.')->controller(BookCategoryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{bookCategory:slug}', 'update')->name('update');
        Route::delete('/{bookCategory:slug}', 'destroy')->name('destroy');
    });

    Route::middleware(StaffOrAdmin::class)->prefix('loans')->name('loans.')->controller(BookLoanController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{loan}/return', 'returnBook')->name('return');
        Route::post('/{loan}/fine/pay', 'payFine')->name('fine.pay');
    });

    Route::middleware(StaffOrAdmin::class)->prefix('reports')->name('reports.')->controller(LibraryFeaturesController::class)->group(function () {
        Route::get('/', 'report')->name('index');
        Route::get('/books.csv', 'exportBooks')->name('books');
        Route::get('/loans.csv', 'exportLoans')->name('loans');
    });

    Route::middleware(AdminOnly::class)->prefix('admin/library')->name('admin.')->controller(LibraryFeaturesController::class)->group(function () {
        Route::get('/audit-log', 'auditLog')->name('audit-log');
        Route::get('/backups', 'backups')->name('backups');
        Route::post('/backups', 'createBackup')->name('backups.create');
        Route::get('/backups/download', 'downloadBackup')->name('backups.download');
        Route::post('/backups/restore', 'restoreBackup')->name('backups.restore');
    });

    Route::middleware(AdminOnly::class)->prefix('admin/settings')->name('admin.')->controller(LoginBrandingController::class)->group(function () {
        Route::get('/login-branding', 'edit')->name('login-branding');
        Route::put('/login-branding', 'update')->name('login-branding.update');
    });

    Route::middleware(AdminOnly::class)->prefix('users')->name('users.')->controller(UserManagementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{user}/edit', 'edit')->name('edit');
        Route::put('/{user}', 'update')->name('update');
        Route::delete('/{user}', 'destroy')->name('destroy');
    });

    Route::controller(AccountController::class)->prefix('account')->name('account.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

    Route::middleware(AdminOnly::class)->get('/users/{user}/history', [BookActivityController::class, 'history'])->name('users.history');

    Route::middleware(AdminOnly::class)->prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });

    Route::post('/logout', function () {
        Attendance::clockOut(request()->user());
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BookReservationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\ApiController;
use App\Http\Middleware\StaffOrAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [ApiAuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [ApiAuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/auth/token', [ApiAuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/user', [ApiAuthController::class, 'user']);
    Route::delete('/auth/token', [ApiAuthController::class, 'logout']);

    Route::get('/internal/users', function (Request $request) {
        return response()->json([
            'data' => User::query()->select(['id', 'name', 'email', 'role', 'is_admin'])->orderBy('name')->get(),
        ]);
    });

    Route::middleware(StaffOrAdmin::class)->group(function () {
        Route::get('/internal/transactions', [ApiController::class, 'transactions']);
        Route::get('/internal/transactions/{id}', [ApiController::class, 'transactionDetail']);
        Route::post('/internal/transactions', [ApiController::class, 'createTransaction']);
        Route::post('/internal/transactions/{id}', [ApiController::class, 'createTransaction']);
        Route::put('/internal/transactions', [ApiController::class, 'updateTransaction']);
        Route::put('/internal/transactions/{id}', [ApiController::class, 'updateTransaction']);
        Route::patch('/internal/transactions', [ApiController::class, 'returnTransaction']);
        Route::patch('/internal/transactions/{id}', [ApiController::class, 'updateTransaction']);
        Route::delete('/internal/transactions', [ApiController::class, 'voidTransaction']);
        Route::delete('/internal/transactions/{id}', [ApiController::class, 'voidTransaction']);
    });

    Route::get('/categories', [ApiController::class, 'categories']);
    Route::get('/categories/{bookCategory:slug}', [ApiController::class, 'showCategory']);

    Route::middleware(StaffOrAdmin::class)->group(function () {
        Route::post('/categories', [ApiController::class, 'storeCategory']);
        Route::put('/categories/{bookCategory:slug}', [ApiController::class, 'updateCategory']);
        Route::patch('/categories/{bookCategory:slug}', [ApiController::class, 'updateCategory']);
        Route::delete('/categories/{bookCategory:slug}', [ApiController::class, 'deleteCategory']);
    });

    Route::get('/books', [ApiController::class, 'books']);
    Route::get('/books/{book:slug}', [ApiController::class, 'book']);
    Route::get('/loans', [ApiController::class, 'loans']);
    Route::get('/loans/{loan}', [ApiController::class, 'showLoan']);
    Route::get('/reservations', [BookReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [BookReservationController::class, 'show']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/{attendance}', [AttendanceController::class, 'show']);
    Route::get('/transactions', [ApiController::class, 'transactions']);
    Route::post('/loans', [ApiController::class, 'createLoan']);

    Route::middleware(StaffOrAdmin::class)->group(function () {
        Route::apiResource('students', StudentController::class);
        Route::apiResource('reservations', BookReservationController::class)->except(['index', 'show']);
        Route::apiResource('attendances', AttendanceController::class)->except(['index', 'show']);
        Route::post('/loans/{loan}/return', [ApiController::class, 'returnLoan']);
        Route::put('/loans/{loan}', [ApiController::class, 'updateLoan']);
        Route::patch('/loans/{loan}', [ApiController::class, 'updateLoan']);
        Route::delete('/loans/{loan}', [ApiController::class, 'deleteLoan']);
        Route::get('/reports/loans', [ApiController::class, 'report']);
        Route::post('/books', [ApiController::class, 'storeBook']);
        Route::put('/books/{book:slug}', [ApiController::class, 'updateBook']);
        Route::patch('/books/{book:slug}', [ApiController::class, 'updateBook']);
        Route::delete('/books/{book:slug}', [ApiController::class, 'deleteBook']);
    });
});

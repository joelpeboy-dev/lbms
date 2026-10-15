<?php

use App\Http\Controllers\ApiController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\StaffOrAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [ApiController::class, 'issueToken'])->middleware('throttle:6,1');

Route::middleware(AuthenticateApiToken::class)->group(function () {
    Route::delete('/auth/token', [ApiController::class, 'revokeToken']);
    Route::get('/categories', [ApiController::class, 'categories']);
    Route::get('/books', [ApiController::class, 'books']);
    Route::get('/books/{book:slug}', [ApiController::class, 'book']);
    Route::get('/loans', [ApiController::class, 'loans']);
    Route::post('/loans', [ApiController::class, 'createLoan']);

    Route::middleware(StaffOrAdmin::class)->group(function () {
        Route::post('/loans/{loan}/return', [ApiController::class, 'returnLoan']);
        Route::get('/reports/loans', [ApiController::class, 'report']);
    });
});

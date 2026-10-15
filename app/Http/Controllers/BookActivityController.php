<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookActivity;
use App\Models\LibraryAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookActivityController extends Controller
{
    public function favorite(Book $book): RedirectResponse
    {
        $activity = BookActivity::query()->where([
            'user_id' => request()->user()->id,
            'book_id' => $book->id,
            'type' => 'favorite',
        ])->first();

        if ($activity) {
            $activity->delete();
            LibraryAuditLog::record('book.unfavorited', $book, [], request()->user());
        } else {
            BookActivity::create([
                'user_id' => request()->user()->id,
                'book_id' => $book->id,
                'type' => 'favorite',
            ]);
            LibraryAuditLog::record('book.favorited', $book, [], request()->user());
        }

        return back();
    }

    public function history(User $user): View
    {
        return view('users.history', [
            'user' => $user,
            'favorites' => BookActivity::query()->with('book')->where('user_id', $user->id)->where('type', 'favorite')->latest()->get(),
            'history' => BookActivity::query()->with('book')->where('user_id', $user->id)->where('type', 'viewed')->latest()->get(),
        ]);
    }
}

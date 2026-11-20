<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function currentUser(): ?User
    {
        return Auth::user();
    }

    protected function isAdminUser(?User $user = null): bool
    {
        $user = $user ?? $this->currentUser();

        return $user?->isAdmin() ?? false;
    }
}

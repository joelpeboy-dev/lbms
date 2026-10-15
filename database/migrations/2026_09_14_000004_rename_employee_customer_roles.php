<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'employee')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'customer')->update(['role' => 'student']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'staff')->update(['role' => 'employee']);
        DB::table('users')->where('role', 'student')->update(['role' => 'customer']);
    }
};
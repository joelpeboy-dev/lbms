<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('books')->where('category', 'book')->delete();
    }

    public function down(): void
    {
        // Other Books were seeded content and are intentionally not restored.
    }
};
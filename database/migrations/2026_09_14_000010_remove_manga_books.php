<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('books')->where('category', 'manga')->delete();
    }

    public function down(): void
    {
        // Manga titles were seeded content and are intentionally not restored.
    }
};

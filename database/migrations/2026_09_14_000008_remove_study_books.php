<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('books')->where('category', 'study')->delete();
    }

    public function down(): void
    {
        // Study titles were seeded content and are intentionally not restored.
    }
};

<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_admin_can_create_and_restore_a_valid_database_backup_with_safety_copy(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'library-backup-test-');
        $this->assertNotFalse($databasePath);
        unlink($databasePath);

        $originalBackups = Storage::disk('local')->files('library-backups');
        config([
            'database.connections.library_backup_test' => array_merge(
                config('database.connections.sqlite'),
                ['database' => $databasePath],
            ),
            'database.default' => 'library_backup_test',
        ]);
        DB::purge('library_backup_test');

        try {
            $this->artisan('migrate:fresh', ['--database' => 'library_backup_test', '--force' => true])
                ->assertExitCode(0);

            $admin = User::factory()->create(['role' => 'admin']);
            $originalBook = Book::create([
                'slug' => 'original-database-title',
                'category' => 'book',
                'title' => 'Original Database Title',
                'author' => 'Test Author',
                'description' => 'Original database content.',
                'content' => 'Original story content.',
                'total_copies' => 1,
            ]);

            $this->actingAs($admin)->post(route('admin.backups.create'))
                ->assertRedirect(route('admin.backups'))
                ->assertSessionHas('success');
            $createdBackups = array_values(array_diff(
                Storage::disk('local')->files('library-backups'),
                $originalBackups,
            ));
            $this->assertNotEmpty($createdBackups);
            $backupPath = $createdBackups[0] ?? '';
            $this->assertFileExists(Storage::disk('local')->path($backupPath));

            Book::create([
                'slug' => 'later-database-title',
                'category' => 'book',
                'title' => 'Later Database Title',
                'author' => 'Test Author',
                'description' => 'Later database content.',
                'content' => 'Later story content.',
                'total_copies' => 1,
            ]);

            $this->post(route('admin.backups.restore'), [
                'backup' => new UploadedFile(
                    Storage::disk('local')->path($backupPath),
                    'library-restore.sqlite',
                    'application/vnd.sqlite3',
                    null,
                    true,
                ),
            ])->assertRedirect(route('admin.backups'))->assertSessionHas('success');

            $this->assertDatabaseHas('books', ['id' => $originalBook->id, 'title' => $originalBook->title]);
            $this->assertDatabaseMissing('books', ['slug' => 'later-database-title']);
            $this->assertSame(2, count(array_diff(
                Storage::disk('local')->files('library-backups'),
                $originalBackups,
            )));
        } finally {
            DB::purge('library_backup_test');
            config(['database.default' => 'sqlite']);

            $createdBackups = array_values(array_diff(
                Storage::disk('local')->files('library-backups'),
                $originalBackups,
            ));
            if ($createdBackups !== []) {
                Storage::disk('local')->delete($createdBackups);
            }

            foreach ([$databasePath, $databasePath.'-wal', $databasePath.'-shm'] as $path) {
                if (is_file($path) && ! unlink($path)) {
                    throw new \RuntimeException('A temporary test database file could not be removed.');
                }
            }
        }
    }
}

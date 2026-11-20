<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_admin_can_create_and_restore_a_valid_database_backup_with_safety_copy(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'library-backup-test-');
        $this->assertNotFalse($databasePath);
        unlink($databasePath);
        $legacyBackupPath = null;

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
            $category = BookCategory::create([
                'slug' => 'restorable-category',
                'name' => 'Restorable Category',
            ]);
            $originalBook = Book::create([
                'slug' => 'original-database-title',
                'category' => 'book',
                'genre' => $category->slug,
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

            $legacyBackupPath = tempnam(sys_get_temp_dir(), 'library-legacy-backup-');
            $this->assertNotFalse($legacyBackupPath);
            $this->assertTrue(copy(Storage::disk('local')->path($backupPath), $legacyBackupPath));
            $legacyDatabase = new PDO('sqlite:'.$legacyBackupPath);
            $legacyDatabase->exec('DROP TABLE book_categories');
            $legacyDatabase->exec("DELETE FROM migrations WHERE migration = '2026_10_20_015734_create_book_categories_table'");
            unset($legacyDatabase);

            $this->post(route('admin.backups.restore'), [
                'backup' => new UploadedFile($legacyBackupPath, 'legacy-library.sqlite', 'application/vnd.sqlite3', null, true),
            ])->assertUnprocessable();
            $this->assertDatabaseHas('books', ['slug' => 'later-database-title']);

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
            $this->assertDatabaseHas('book_categories', [
                'slug' => 'restorable-category',
                'name' => 'Restorable Category',
            ]);
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

            if ($legacyBackupPath !== null && is_file($legacyBackupPath) && ! unlink($legacyBackupPath)) {
                throw new \RuntimeException('The temporary legacy backup file could not be removed.');
            }
        }
    }
}

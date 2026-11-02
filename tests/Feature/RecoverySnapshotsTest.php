<?php

namespace Tests\Feature;

use App\Services\RecoverySnapshots;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RecoverySnapshotsTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir().'/hms-recovery-test-'.Str::uuid();
        $this->app->useStoragePath($this->temporary);
        File::ensureDirectoryExists(storage_path('app/backups/snapshot'), 0700, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function snapshot(): string
    {
        $directory = storage_path('app/backups/snapshot');
        file_put_contents($directory.'/database.sql.gz', gzencode('CREATE TABLE restore_fixture (id INT);'));
        File::ensureDirectoryExists($this->temporary.'/private');
        file_put_contents($this->temporary.'/private/attachment.txt', 'Private fixture');
        (new Process(['tar', '-czf', $directory.'/private-files.tar.gz', '-C', $this->temporary.'/private', '.']))->mustRun();
        file_put_contents($directory.'/environment', 'APP_ENV=testing');
        file_put_contents($directory.'/manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database' => hash_file('sha256', $directory.'/database.sql.gz'), 'private_files' => hash_file('sha256', $directory.'/private-files.tar.gz'), 'environment' => hash_file('sha256', $directory.'/environment')], JSON_THROW_ON_ERROR));

        return $directory;
    }

    public function test_snapshot_verification_detects_modified_files_and_missing_manifests(): void
    {
        $directory = $this->snapshot();
        $this->artisan('app:backup-verify', ['snapshot' => $directory])->assertSuccessful();
        $this->assertNotNull(app(RecoverySnapshots::class)->latest()['verified_at']);
        file_put_contents($directory.'/environment', 'MODIFIED');
        $this->artisan('app:backup-verify', ['snapshot' => $directory])->assertFailed();
        File::delete($directory.'/manifest.json');
        $this->assertNull(app(RecoverySnapshots::class)->latest());
        $this->artisan('app:backup-verify', ['snapshot' => $directory])->assertFailed();
    }

    public function test_verification_refuses_a_directory_outside_private_backups(): void
    {
        $this->snapshot();
        $this->artisan('app:backup-verify', ['snapshot' => $this->temporary.'/private'])->assertFailed();
        $this->assertFileDoesNotExist($this->temporary.'/private/verification.json');
    }
}

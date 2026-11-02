<?php

namespace App\Console\Commands;

use App\Services\RecoverySnapshots;
use Illuminate\Console\Command;

class VerifyBackup extends Command
{
    protected $signature = 'app:backup-verify {snapshot? : Directory of the private recovery snapshot}';

    protected $description = 'Verify backup checksums and readable archives without changing the database';

    public function handle(RecoverySnapshots $snapshots): int
    {
        $directory = $this->argument('snapshot') ?: ($snapshots->directories()[0] ?? null);
        if (! is_string($directory)) {
            $this->error('No snapshot is available. Run app:backup first.');

            return self::FAILURE;
        }
        try {
            $snapshots->verify($directory);
        } catch (\Throwable) {
            $this->error('Verification failed. Keep the last verified snapshot and investigate this backup before relying on it.');

            return self::FAILURE;
        }
        $this->info('Database, private files, and environment checksums verified; archives are readable. This check does not replace a restore drill.');

        return self::SUCCESS;
    }
}

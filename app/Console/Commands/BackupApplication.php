<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupApplication extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Create a private database, attachment, and environment recovery snapshot';

    public function handle(): int
    {
        $directory = storage_path('app/backups/'.now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
        File::ensureDirectoryExists($directory, 0700, true);
        chmod($directory, 0700);
        $options = $directory.'/.mysql-options';
        $db = config('database.connections.mysql');
        $quote = fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
        file_put_contents($options, "[client]\nhost=".$quote((string) $db['host'])."\nport=".(int) $db['port']."\nuser=".$quote((string) $db['username'])."\npassword=".$quote((string) $db['password'])."\n");
        chmod($options, 0600);
        $sql = $directory.'/database.sql.gz';
        $stream = gzopen($sql, 'wb6');
        if ($stream === false) {
            File::delete($options);
            $this->error('Cannot open the private backup output.');

            return self::FAILURE;
        }
        chmod($sql, 0600);
        try {
            $process = new Process(['mysqldump', '--defaults-extra-file='.$options, '--single-transaction', '--skip-lock-tables', '--no-tablespaces', (string) $db['database']]);
            $process->setTimeout(600);
            $process->run(function (string $type, string $bytes) use ($stream, $process): void {
                if ($type === Process::OUT && gzwrite($stream, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException('Backup storage write failed.');
                }
                $process->clearOutput();
            });
            gzclose($stream);
            $stream = null;
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('Database backup failed. Check database access and mysqldump availability.');
            }
            $tar = new Process(['tar', '-czf', $directory.'/private-files.tar.gz', '-C', storage_path('app/private'), '.']);
            $tar->setTimeout(600)->mustRun();
            chmod($directory.'/private-files.tar.gz', 0600);
            File::copy(base_path('.env'), $directory.'/environment');
            chmod($directory.'/environment', 0600);
            $manifest = ['created_at' => now()->utc()->toIso8601String(), 'database' => hash_file('sha256', $sql), 'private_files' => hash_file('sha256', $directory.'/private-files.tar.gz'), 'environment' => hash_file('sha256', $directory.'/environment')];
            file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($directory.'/manifest.json', 0600);
        } catch (\Throwable) {
            $this->error('Backup incomplete. Check disk space, mysqldump, and private storage. This snapshot must not be used for recovery.');

            return self::FAILURE;
        } finally {
            if (is_resource($stream)) {
                gzclose($stream);
            }
            File::delete($options);
        }
        $this->info('Private recovery snapshot created: '.$directory);

        return self::SUCCESS;
    }
}

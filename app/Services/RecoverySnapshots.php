<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class RecoverySnapshots
{
    /** @return array{created_at: string, verified_at: ?string}|null */
    public function latest(): ?array
    {
        foreach ($this->directories() as $directory) {
            $manifest = $this->json($directory.'/manifest.json');
            if (! is_string($manifest['created_at'] ?? null) || strtotime($manifest['created_at']) === false) {
                continue;
            }
            $verified = $this->json($directory.'/verification.json');

            return ['created_at' => $manifest['created_at'], 'verified_at' => ($verified['manifest_hash'] ?? '') === hash_file('sha256', $directory.'/manifest.json') && is_string($verified['verified_at'] ?? null) ? $verified['verified_at'] : null];
        }

        return null;
    }

    /** @return list<string> */
    public function directories(): array
    {
        $paths = is_dir(storage_path('app/backups')) ? File::directories(storage_path('app/backups')) : [];
        rsort($paths);

        return $paths;
    }

    public function verify(string $directory): void
    {
        $root = realpath(storage_path('app/backups'));
        $directory = realpath($directory);
        if ($root === false || $directory === false || dirname($directory) !== $root) {
            throw new \RuntimeException('Choose a snapshot inside the private backup directory.');
        }
        $manifest = $this->json($directory.'/manifest.json');
        foreach (['database' => 'database.sql.gz', 'private_files' => 'private-files.tar.gz', 'environment' => 'environment'] as $key => $file) {
            $expected = $manifest[$key] ?? null;
            $actual = is_file($directory.'/'.$file) ? hash_file('sha256', $directory.'/'.$file) : false;
            if (! is_string($expected) || ! preg_match('/^[a-f0-9]{64}$/D', $expected) || ! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new \RuntimeException('Snapshot checksum verification failed: '.$key.'.');
            }
        }
        $stream = gzopen($directory.'/database.sql.gz', 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot read the database archive.');
        }
        try {
            while (! gzeof($stream)) {
                if (@gzread($stream, 1048576) === false) {
                    throw new \RuntimeException('The database archive is damaged.');
                }
            }
        } finally {
            gzclose($stream);
        }
        $archive = new Process(['tar', '-tzf', $directory.'/private-files.tar.gz']);
        $archive->setTimeout(600)->mustRun();
        foreach (explode("\n", trim($archive->getOutput())) as $path) {
            if (str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
                throw new \RuntimeException('The private-file archive contains an unsafe path.');
            }
        }
        $verification = $directory.'/verification.json';
        file_put_contents($verification, json_encode(['verified_at' => now()->utc()->toIso8601String(), 'manifest_hash' => hash_file('sha256', $directory.'/manifest.json')], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        chmod($verification, 0600);
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $value = json_decode(File::get($path), true);

        return is_array($value) ? $value : [];
    }
}

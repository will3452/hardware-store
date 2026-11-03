<?php

namespace App\Console\Commands;

use App\Services\EmailOutbox;
use Illuminate\Console\Command;

class DrainEmail extends Command
{
    protected $signature = 'app:mail-drain {--limit=10} {--max-time=35}';

    protected $description = 'Deliver due account and opted-in announcement emails using a bounded SMTP drain';

    public function handle(EmailOutbox $outbox): int
    {
        $count = $outbox->drain(max(1, min(100, (int) $this->option('limit'))), max(1, min(45, (int) $this->option('max-time'))));
        $this->info('Email deliveries processed: '.$count);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\SendDuesReminders;
use Illuminate\Console\Command;

class RemindDuesCommand extends Command
{
    protected $signature = 'dues:remind';

    protected $description = 'Email active members who have an outstanding monthly fund balance.';

    public function handle(): int
    {
        SendDuesReminders::dispatch();
        $this->info('Queued dues reminders.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyDues;
use App\Support\BusinessCalendar;
use Illuminate\Console\Command;

class GenerateDuesCommand extends Command
{
    protected $signature = 'dues:generate {--period= : Month to generate, as YYYY-MM. Defaults to the current month.}';

    protected $description = 'Generate monthly dues for all eligible members. Safe to run repeatedly.';

    public function handle(): int
    {
        $period = $this->option('period');

        if ($period !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period) !== 1) {
            $this->error('The period must look like 2026-11.');

            return self::FAILURE;
        }

        $month = $period ?? BusinessCalendar::currentMonth()->format('Y-m');

        GenerateMonthlyDues::dispatch((string) $month);
        $this->info("Queued dues generation for {$month}.");

        return self::SUCCESS;
    }
}

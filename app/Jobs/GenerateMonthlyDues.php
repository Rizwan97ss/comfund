<?php

namespace App\Jobs;

use App\Services\AuditLogger;
use App\Services\DuesService;
use App\Support\BusinessCalendar;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyDues implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $period) {}

    public function uniqueId(): string
    {
        return $this->period;
    }

    public function handle(DuesService $dues, AuditLogger $audit): void
    {
        $created = $dues->generateForPeriod(BusinessCalendar::month($this->period));

        $audit->record('dues.generated', 'finance', payload: ['period' => $this->period, 'obligations_created' => $created]);
    }
}

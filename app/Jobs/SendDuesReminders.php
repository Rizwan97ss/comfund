<?php

namespace App\Jobs;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\DuesReminder;
use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDuesReminders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(ReportService $reports): void
    {
        $reports->membersWithOutstanding()
            ->where('status', AccountStatus::Active)
            ->get()
            ->each(function (User $member): void {
                $member->notify(new DuesReminder(
                    (int) $member->getAttribute('obligated_paise') - (int) $member->getAttribute('allocated_paise') - (int) $member->getAttribute('waived_paise'),
                ));
            });
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Today" and "this month" as the community experiences them, not as UTC does.
 */
final class BusinessCalendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('fund.timezone'))->startOfDay();
    }

    public static function currentMonth(): CarbonImmutable
    {
        return self::today()->startOfMonth();
    }

    public static function month(string $yearMonthOrDate): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($yearMonthOrDate, 0, 7).'-01', (string) config('fund.timezone'))->startOfDay();
    }
}

<?php

use Illuminate\Support\Facades\Schedule;

$timezone = (string) config('fund.timezone');

Schedule::command('dues:generate')->monthlyOn(1, '00:10')->timezone($timezone)->onOneServer();
Schedule::command('dues:remind')->monthlyOn(10, '09:00')->timezone($timezone)->onOneServer();
Schedule::command('audit:verify')->dailyAt('01:00')->timezone($timezone)->withoutOverlapping()->onOneServer();
Schedule::command('ledger:reconcile')->dailyAt('01:15')->timezone($timezone)->withoutOverlapping()->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->timezone($timezone)->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Everything scheduled belongs here rather than in Windows Task Scheduler. The
| deployed server holds exactly one Task Scheduler entry — `schedule:run` every
| minute — so adding recurring work is a PHP change that ships with the code and
| is visible to `php artisan schedule:list`, not an undocumented click in a
| Windows dialog on one machine. See ARCHITECTURE.md §15.
|
*/

Schedule::command('backup:database')
    ->dailyAt((string) config('backup.time'))
    ->withoutOverlapping();

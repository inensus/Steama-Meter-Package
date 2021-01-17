<?php

namespace Inensus\SteamaMeter\Console;

use App\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        parent::schedule($schedule);
        $schedule->command('steama-meter:updatesGetter')->dailyAt('00:30');
        $schedule->command('steama-meter:transactionSync')->everyFiveMinutes()->withoutOverlapping(50)
            ->appendOutputTo(storage_path('logs/cron.log'));

        //
    }
}
<?php

use Illuminate\Support\Facades\Schedule;

// Every minute, claim due monitors and dispatch their checks. onOneServer keeps
// multi-server cron from double-dispatching; withoutOverlapping stops a slow tick
// from stacking on the next one. The per-row atomic claim is the real guarantee.
Schedule::command('uptime:dispatch-checks')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

// Daily SSL expiry sweep for active https monitors.
Schedule::command('uptime:dispatch-ssl')
    ->dailyAt('03:00')
    ->onOneServer();

// Roll completed hours up continuously.
Schedule::command('uptime:rollup hour')
    ->hourlyAt(5)
    ->onOneServer()
    ->withoutOverlapping();

// Daily maintenance, strictly ordered: roll the day up, then prune. The prune
// runs after the daily rollup so nothing is deleted before it is aggregated.
Schedule::command('uptime:rollup day')
    ->dailyAt('00:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('uptime:prune')
    ->dailyAt('00:20')
    ->onOneServer()
    ->withoutOverlapping();

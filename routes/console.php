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

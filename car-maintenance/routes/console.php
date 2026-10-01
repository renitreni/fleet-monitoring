<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('oil-changes:check')->dailyAt('08:00');

Schedule::command('trips:prune-locations')->everyMinute()->withoutOverlapping();

Schedule::command('blog:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('analytics:prune-page-views')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('analytics:update-country-database')->dailyAt('03:30')->withoutOverlapping();

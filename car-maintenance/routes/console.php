<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('oil-changes:check')->dailyAt('08:00');

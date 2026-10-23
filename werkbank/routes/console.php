<?php

use Illuminate\Support\Facades\Schedule;

// Geplande taken. Draaien via de scheduler-container (Docker) of cron: * * * * * php artisan schedule:run
Schedule::command('werkbank:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('werkbank:contracten-factureren')->dailyAt('03:00')->withoutOverlapping();

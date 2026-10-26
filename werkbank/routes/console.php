<?php

use App\Support\Ponto\PontoClient;
use Illuminate\Support\Facades\Schedule;

// Geplande taken. Draaien via de scheduler-container (Docker) of cron: * * * * * php artisan schedule:run
Schedule::command('werkbank:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('werkbank:contracten-factureren')->dailyAt('03:00')->withoutOverlapping();
// Bankkoppeling (alleen lezen): Ponto synchroniseert zelf 4× per dag met de bank; Werkbank leest alleen.
Schedule::command('werkbank:bank-ophalen')->hourly()->weekdays()->between('7:00', '19:00')->withoutOverlapping()
    ->when(fn () => PontoClient::configured());

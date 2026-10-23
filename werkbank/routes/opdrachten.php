<?php

use App\Http\Controllers\HourController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Opdrachten
Route::middleware('can:opdrachten.bekijken')->group(function () {
    Route::resource('opdrachten', ProjectController::class)->parameters(['opdrachten' => 'opdracht'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:opdrachten.bewerken');
    Route::get('/opdrachten/{opdracht}/pdf', [ProjectController::class, 'pdf'])->name('opdrachten.pdf');
    Route::middleware('can:opdrachten.bewerken')->group(function () {
        Route::post('/opdrachten/{opdracht}/archiveren', [ProjectController::class, 'archive'])->name('opdrachten.archiveren');
        Route::post('/offertes/{offerte}/opdracht', [ProjectController::class, 'fromOffer'])->name('opdrachten.uit-offerte');
    });

    // Taken en planning
    Route::resource('taken', TaskController::class)->except(['destroy'])->parameters(['taken' => 'taak'])
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:taken.bewerken');
    Route::middleware('can:taken.bewerken')->group(function () {
        Route::post('/taken/{taak}/afronden', [TaskController::class, 'toggle'])->name('taken.afronden');
        Route::post('/taken/{taak}/inplannen', [TaskController::class, 'schedule'])->name('taken.inplannen');
        Route::post('/planning', [PlanningController::class, 'store'])->name('planning.store');
        Route::delete('/planning/{item}', [PlanningController::class, 'destroy'])->name('planning.destroy');
    });
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
});

// Uren
Route::middleware('can:uren.schrijven')->group(function () {
    Route::get('/uren', [HourController::class, 'index'])->name('uren.index');
    Route::post('/uren', [HourController::class, 'store'])->name('uren.store');
    Route::put('/uren/{uur}', [HourController::class, 'update'])->name('uren.update');
    Route::delete('/uren/{uur}', [HourController::class, 'destroy'])->name('uren.destroy');
    Route::post('/uren/indienen', [HourController::class, 'submit'])->name('uren.indienen');
});
Route::middleware('can:uren.fiatteren')->group(function () {
    Route::get('/uren/fiatteren', [HourController::class, 'approvals'])->name('uren.fiatteren');
    Route::post('/uren/fiatteren', [HourController::class, 'approve'])->name('uren.fiatteren.opslaan');
});

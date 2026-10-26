<?php

use App\Http\Controllers\Beheer\SettingsController;
use App\Http\Controllers\Beheer\StamgegevensController;
use Illuminate\Support\Facades\Route;

// Binnen prefix 'beheer' en naam 'beheer.'
Route::middleware('can:beheer.instellingen')->group(function () {
    Route::get('/instellingen', [SettingsController::class, 'edit'])->name('instellingen.edit');
    Route::put('/instellingen', [SettingsController::class, 'update'])->name('instellingen.update');
    Route::get('/stamgegevens', [StamgegevensController::class, 'index'])->name('stamgegevens.index');
    Route::get('/stamgegevens/{soort}', [StamgegevensController::class, 'show'])->name('stamgegevens.show');
    Route::post('/stamgegevens/{soort}', [StamgegevensController::class, 'store'])->name('stamgegevens.store');
    Route::get('/stamgegevens/{soort}/{id}', [StamgegevensController::class, 'edit'])->name('stamgegevens.edit')->whereNumber('id');
    Route::put('/stamgegevens/{soort}/{id}', [StamgegevensController::class, 'update'])->name('stamgegevens.update')->whereNumber('id');
});

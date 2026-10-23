<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Beheer;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/inloggen', [LoginController::class, 'create'])->name('login');
    Route::post('/inloggen', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'account'])->group(function () {
    Route::post('/uitloggen', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/zoeken', SearchController::class)->name('zoeken');

    // Relaties en contactpersonen
    Route::middleware('can:relaties.bekijken')->group(function () {
        Route::resource('relaties', CompanyController::class)->parameters(['relaties' => 'relatie'])
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:relaties.bewerken');
        Route::post('/relaties/{relatie}/notities', [CompanyController::class, 'storeNote'])->name('relaties.notities.store');
        Route::resource('contactpersonen', ContactController::class)->parameters(['contactpersonen' => 'contactpersoon']);
    });

    // Bestanden (rechten worden per gekoppeld record gecontroleerd)
    Route::get('/bestanden/{bestand}', [FileController::class, 'show'])->name('bestanden.show');
    Route::post('/bestanden', [FileController::class, 'store'])->name('bestanden.store');

    require __DIR__.'/verkoop.php';
    require __DIR__.'/financieel.php';

    // Beheer
    Route::prefix('beheer')->name('beheer.')->group(function () {
        Route::middleware('can:beheer.gebruikers')->group(function () {
            Route::resource('gebruikers', Beheer\UserController::class)->except(['show', 'destroy'])->parameters(['gebruikers' => 'gebruiker']);
            Route::post('/gebruikers/{gebruiker}/wachtwoord', [Beheer\UserController::class, 'resetPassword'])->name('gebruikers.wachtwoord');
        });
        Route::middleware('can:beheer.instellingen')->group(function () {
            Route::resource('medewerkers', Beheer\EmployeeController::class)->except(['show', 'destroy'])->parameters(['medewerkers' => 'medewerker']);
        });
        Route::middleware('can:beheer.import')->group(function () {
            Route::get('/import', [Beheer\ImportController::class, 'index'])->name('import.index');
            Route::post('/import', [Beheer\ImportController::class, 'store'])->name('import.store');
            Route::get('/import/{run}', [Beheer\ImportController::class, 'show'])->name('import.show');
        });
        Route::get('/logboek', Beheer\AuditLogController::class)->middleware('can:beheer.auditlog')->name('logboek');
        require __DIR__.'/beheer.php';
    });
});

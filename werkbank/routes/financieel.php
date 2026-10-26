<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Bank (Ponto, alleen lezen): transacties en koppelvoorstellen
Route::middleware('can:bank.koppelen')->group(function () {
    Route::get('/bank', [BankController::class, 'index'])->name('bank.index');
    Route::post('/bank/ophalen', [BankController::class, 'fetch'])->name('bank.ophalen')->middleware('throttle:6,1');
    Route::post('/bank/zeker-boeken', [BankController::class, 'bookCertain'])->name('bank.zeker');
    Route::post('/bank/{transactie}/boeken', [BankController::class, 'book'])->name('bank.boeken');
    Route::post('/bank/{transactie}/negeren', [BankController::class, 'ignore'])->name('bank.negeren');
    Route::post('/bank/{transactie}/heropenen', [BankController::class, 'reopen'])->name('bank.heropenen');
});

// Verkoopfacturen en contracten
Route::middleware('can:facturen.bekijken')->group(function () {
    Route::resource('facturen', InvoiceController::class)->parameters(['facturen' => 'factuur'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:facturen.bewerken');
    Route::get('/facturen/{factuur}/pdf', [InvoiceController::class, 'pdf'])->name('facturen.pdf');
    Route::get('/facturen/{factuur}/ubl', [InvoiceController::class, 'ubl'])->name('facturen.ubl');
    Route::middleware('can:facturen.bewerken')->group(function () {
        Route::post('/facturen/{factuur}/definitief', [InvoiceController::class, 'finalize'])->name('facturen.definitief');
        Route::post('/facturen/{factuur}/herinnering', [InvoiceController::class, 'remind'])->name('facturen.herinnering');
        Route::post('/facturen/{factuur}/crediteren', [InvoiceController::class, 'credit'])->name('facturen.credit');
        Route::post('/facturen/{factuur}/betalingen', [InvoiceController::class, 'storePayment'])->name('facturen.betalingen.store');
        Route::delete('/facturen/{factuur}/betalingen/{payment}', [InvoiceController::class, 'destroyPayment'])->name('facturen.betalingen.destroy')->whereNumber('payment');
        Route::post('/contracten/factureren', [ContractController::class, 'invoiceDue'])->name('contracten.factureren');
    });
    Route::resource('contracten', ContractController::class)->except(['destroy'])->parameters(['contracten' => 'contract'])
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:facturen.bewerken');
});

require __DIR__.'/inkoop.php';

// Overzichten
Route::middleware('can:rapporten.bekijken')->prefix('overzichten')->name('rapporten.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    // Recht per soort wordt in de controller gecontroleerd (debiteuren: facturen, crediteuren: inkoop).
    Route::get('/open/{soort}', [ReportController::class, 'open'])->name('open')->whereIn('soort', ['debiteuren', 'crediteuren']);
    Route::get('/omzet', [ReportController::class, 'revenue'])->name('omzet')->middleware('can:facturen.bekijken');
    Route::get('/pijplijn', [ReportController::class, 'pipeline'])->name('pijplijn')->middleware('can:offertes.bekijken');
    Route::get('/uren', [ReportController::class, 'hours'])->name('uren')->middleware('can:uren.fiatteren');
});
Route::get('/memoriaal-en-kosten', [BookingController::class, 'index'])->name('boekingen.index')->middleware('can:rapporten.bekijken');

<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// Verkoopfacturen en contracten
Route::middleware('can:facturen.bekijken')->group(function () {
    Route::resource('facturen', InvoiceController::class)->parameters(['facturen' => 'factuur'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:facturen.bewerken');
    Route::get('/facturen/{factuur}/pdf', [InvoiceController::class, 'pdf'])->name('facturen.pdf');
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

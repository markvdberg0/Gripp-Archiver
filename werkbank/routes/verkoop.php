<?php

use App\Http\Controllers\OfferController;
use App\Http\Controllers\PacketController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Producten en pakketten
Route::middleware('can:producten.bekijken')->group(function () {
    Route::resource('producten', ProductController::class)->except(['destroy'])->parameters(['producten' => 'product'])
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:producten.bewerken');
    Route::resource('pakketten', PacketController::class)->except(['destroy'])->parameters(['pakketten' => 'pakket'])
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:producten.bewerken');
});

// Offertes
Route::middleware('can:offertes.bekijken')->group(function () {
    Route::resource('offertes', OfferController::class)->parameters(['offertes' => 'offerte'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:offertes.bewerken');
    Route::get('/offertes/{offerte}/pdf', [OfferController::class, 'pdf'])->name('offertes.pdf');
    Route::middleware('can:offertes.bewerken')->group(function () {
        Route::post('/offertes/{offerte}/status/{actie}', [OfferController::class, 'transition'])->name('offertes.status')
            ->whereIn('actie', ['verzonden', 'geaccepteerd', 'afgewezen', 'heropenen', 'archiveren']);
        Route::post('/offertes/{offerte}/kopie', [OfferController::class, 'copy'])->name('offertes.kopie');
    });
});

require __DIR__.'/opdrachten.php';

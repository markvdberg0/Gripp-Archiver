<?php

use App\Http\Controllers\PaymentFileController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:inkoop.goedkeuren')->group(function () {
    Route::get('/inkoopfacturen-betalen', [PaymentFileController::class, 'index'])->name('inkoopfacturen.betalen');
    Route::post('/inkoopfacturen-betalen', [PaymentFileController::class, 'store'])->name('inkoopfacturen.betalen.store');
});

Route::middleware('can:inkoop.bekijken')->group(function () {
    Route::resource('inkoopopdrachten', PurchaseOrderController::class)->parameters(['inkoopopdrachten' => 'inkoopopdracht'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:inkoop.bewerken');
    Route::get('/inkoopopdrachten/{inkoopopdracht}/pdf', [PurchaseOrderController::class, 'pdf'])->name('inkoopopdrachten.pdf');
    Route::post('/inkoopopdrachten/{inkoopopdracht}/status/{status}', [PurchaseOrderController::class, 'status'])->name('inkoopopdrachten.status')->middleware('can:inkoop.bewerken');

    Route::resource('inkoopfacturen', PurchaseInvoiceController::class)->parameters(['inkoopfacturen' => 'inkoopfactuur'])
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:inkoop.bewerken');
    Route::middleware('can:inkoop.bewerken')->group(function () {
        Route::post('/inkoopfacturen/{inkoopfactuur}/fase/{actie}', [PurchaseInvoiceController::class, 'transition'])->name('inkoopfacturen.fase')
            ->whereIn('actie', ['indienen', 'goedkeuren', 'verwerken', 'terug', 'hold', 'vrijgeven']);
        Route::post('/inkoopfacturen/{inkoopfactuur}/betalingen', [PurchaseInvoiceController::class, 'storePayment'])->name('inkoopfacturen.betalingen.store');
    });
});

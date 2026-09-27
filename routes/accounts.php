<?php

use App\Http\Controllers\Accounts\CustomerController;
use App\Http\Controllers\Accounts\InvoiceController;
use App\Http\Controllers\Accounts\ProductController;
use Illuminate\Support\Facades\Route;

/*
 * Accounts: invoices, and the customers and products they are raised from.
 * Each gated by its own permissions, one per action (App\Support\Permissions).
 */
Route::middleware(['auth'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::redirect('/', '/accounts/invoices')->name('home');

    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:invoices.view')->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->middleware('can:invoices.create')->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->middleware('can:invoices.create')->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('can:invoices.view')->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('can:invoices.view')->name('invoices.pdf');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('can:invoices.edit')->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('can:invoices.edit')->name('invoices.update');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('can:invoices.delete')->name('invoices.destroy');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('can:invoices.send')->name('invoices.send');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->middleware('can:invoices.send')->name('invoices.mark-sent');
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware('can:invoices.edit')->name('invoices.mark-paid');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('can:invoices.edit')->name('invoices.cancel');

    foreach (['customers' => CustomerController::class, 'products' => ProductController::class] as $module => $controller) {
        $param = rtrim($module, 's');
        Route::get($module, [$controller, 'index'])->middleware("can:{$module}.view")->name("{$module}.index");
        Route::post($module, [$controller, 'store'])->middleware("can:{$module}.create")->name("{$module}.store");
        Route::put("{$module}/{{$param}}", [$controller, 'update'])->middleware("can:{$module}.edit")->name("{$module}.update");
        Route::patch("{$module}/{{$param}}/active", [$controller, 'active'])->middleware("can:{$module}.edit")->name("{$module}.active");
        Route::delete("{$module}/{{$param}}", [$controller, 'destroy'])->middleware("can:{$module}.delete")->name("{$module}.destroy");
    }
});

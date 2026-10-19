<?php

use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

// Bounded synthetic preview switch; absent or route-cached configuration stays closed.
config(['sales.synthetic_drafts' => filter_var(env('ARASYA_SYNTHETIC_SALES', false), FILTER_VALIDATE_BOOL)]);

Route::prefix('crm/leads/{lead}/quotations')->name('crm.quotations.')->group(function (): void {
    Route::get('/', [QuotationController::class, 'index'])->name('index');
    Route::post('/', [QuotationController::class, 'store'])->name('store');
    Route::get('/create', [QuotationController::class, 'create'])->name('create');
    Route::get('/{quotation}/edit', [QuotationController::class, 'edit'])->name('edit');
    Route::patch('/{quotation}', [QuotationController::class, 'update'])->name('update');
    Route::get('/{quotation}/preview', [QuotationController::class, 'preview'])->name('preview');
    Route::post('/{quotation}/approve', [QuotationController::class, 'disabled'])->name('approve');
    Route::post('/{quotation}/send', [QuotationController::class, 'disabled'])->name('send');
});

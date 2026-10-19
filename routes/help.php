<?php

use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('help')->name('help.')->group(function (): void {
    Route::get('/', [HelpController::class, 'index'])->name('index');
    Route::get('/articles/{article}', [HelpController::class, 'article'])->name('articles.show');
    Route::get('/api/progress/{tour}', [HelpController::class, 'progress'])->name('progress.show');
    Route::patch('/api/progress/{tour}', [HelpController::class, 'saveProgress'])->name('progress.update');
});

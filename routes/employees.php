<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeWorkController;
use Illuminate\Support\Facades\Route;

Route::prefix('employees')->name('employees.')->group(function (): void {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');
    Route::get('/work', [EmployeeWorkController::class, 'index'])->name('work');
    Route::get('/workers', [EmployeeController::class, 'workers'])->name('workers');
    Route::get('/create', [EmployeeController::class, 'create'])->name('create');
    Route::post('/', [EmployeeController::class, 'store'])->name('store');
    Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
    Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
    Route::post('/{employee}', [EmployeeController::class, 'update'])->name('update');
    Route::post('/{employee}/evidence', [EmployeeController::class, 'evidence'])->name('evidence');
});

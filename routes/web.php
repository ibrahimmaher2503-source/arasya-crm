<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AccessSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\TaskCycleController;
use App\Http\Controllers\VerticalCycleRecordController;
use App\Http\Controllers\VerticalCycleRecordPageController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

require __DIR__.'/crm.php';
require __DIR__.'/employees.php';

Route::get('/up', static function (): Response {
    return new Response('Application up', Response::HTTP_OK, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('foundation.health');

Route::post('/vertical/cycle-records', [VerticalCycleRecordController::class, 'store']);
Route::get('/vertical/cycle-records', VerticalCycleRecordPageController::class);

Route::get('/dev/design-system', DesignSystemController::class)->name('development.design-system');

Route::get('/tasks', [TaskCycleController::class, 'index'])->name('tasks.index');
Route::get('/tasks/create', [TaskCycleController::class, 'create'])->name('tasks.create');
Route::post('/tasks', [TaskCycleController::class, 'store'])->name('tasks.store');
Route::get('/tasks/{task}', [TaskCycleController::class, 'show'])->name('tasks.show');
Route::post('/tasks/{task}/start', [TaskCycleController::class, 'start'])->name('tasks.start');
Route::post('/tasks/{task}/assign', [TaskCycleController::class, 'assign'])->name('tasks.assign');
Route::post('/tasks/{task}/evidence', [TaskCycleController::class, 'evidence'])->name('tasks.evidence');
Route::post('/tasks/{task}/complete', [TaskCycleController::class, 'complete'])->name('tasks.complete');
Route::get('/tasks/{task}/evidence/{evidence}', [TaskCycleController::class, 'download'])->name('tasks.download');
Route::post('/tasks/{task}/assistant/preview', [TaskCycleController::class, 'assistantPreview'])->name('tasks.assistant.preview');
Route::post('/tasks/{task}/assistant/confirm', [TaskCycleController::class, 'assistantConfirm'])->name('tasks.assistant.confirm');

Route::get('/', [DashboardController::class, 'home']);
Route::get('/dashboard', DashboardController::class)->name('dashboard');

Route::get('/login', [AccessSessionController::class, 'create'])->name('login');
Route::post('/login', [AccessSessionController::class, 'store']);
Route::post('/logout', [AccessSessionController::class, 'destroy']);
Route::get('/access', [AccessController::class, 'index']);
Route::get('/access/organization', [AccessController::class, 'organization']);
Route::post('/access/organization', [AccessController::class, 'company']);
Route::post('/access/branches', [AccessController::class, 'branch']);
Route::post('/access/branches/{branch}', [AccessController::class, 'branch']);
Route::post('/access/departments', [AccessController::class, 'department']);
Route::post('/access/departments/{department}', [AccessController::class, 'department']);
Route::post('/access/departments/{department}/roles', [AccessController::class, 'departmentRoles']);
Route::get('/access/users', [AccessController::class, 'users']);
Route::post('/access/users', [AccessController::class, 'saveUser']);
Route::get('/access/users/{user}', [AccessController::class, 'user']);
Route::post('/access/users/{user}', [AccessController::class, 'saveUser']);
Route::post('/access/users/{user}/access', [AccessController::class, 'assignment']);
Route::get('/access/roles', [AccessController::class, 'roles']);
Route::post('/access/roles', [AccessController::class, 'saveRole']);
Route::get('/access/roles/{role}', [AccessController::class, 'role']);
Route::post('/access/roles/{role}', [AccessController::class, 'saveRole']);
Route::get('/access/audit', [AccessController::class, 'audit']);

<?php

use App\Http\Controllers\CrmFileController;
use App\Http\Controllers\CrmLeadController;
use App\Http\Controllers\CrmPageController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm')->name('crm.')->group(function (): void {
    Route::get('/pipeline', [CrmPageController::class, 'pipeline'])->name('pipeline');
    Route::post('/pipeline', [CrmPageController::class, 'saveOpportunity'])->name('opportunities.store');
    Route::patch('/pipeline/{opportunity}', [CrmPageController::class, 'saveOpportunity'])->name('opportunities.update');
    Route::post('/pipeline/{opportunity}/transition', [CrmPageController::class, 'transitionOpportunity'])->name('opportunities.transition');
    Route::post('/pipeline/{opportunity}/reopen', [CrmPageController::class, 'reopenOpportunity'])->name('opportunities.reopen');
    Route::get('/leads/{lead}/files', [CrmFileController::class, 'index'])->name('files.index');
    Route::post('/leads/{lead}/files', [CrmFileController::class, 'store'])->name('files.store');
    Route::get('/leads/{lead}/files/{file}/download', [CrmFileController::class, 'download'])->name('files.download');
    Route::get('/', [CrmPageController::class, 'overview'])->name('overview');
    Route::get('/leads/create', [CrmPageController::class, 'create'])->name('leads.create');
    Route::get('/leads/{lead}/edit', [CrmPageController::class, 'edit'])->name('leads.edit');
    Route::get('/leads/{lead}/workspace/{section}', [CrmPageController::class, 'workspace'])->whereIn('section', ['activities', 'notes', 'tasks'])->name('leads.workspace');
    Route::get('/leads', [CrmLeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [CrmLeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [CrmLeadController::class, 'show'])->name('leads.show');
    Route::patch('/leads/{lead}', [CrmLeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/archive', [CrmLeadController::class, 'archive'])->name('leads.archive');
    Route::post('/leads/{lead}/assignment', [CrmLeadController::class, 'assignment'])->name('leads.assignment');
    Route::post('/leads/{lead}/reveal', [CrmLeadController::class, 'reveal'])->name('leads.reveal');
    Route::post('/leads/{lead}/activities', [CrmLeadController::class, 'activity'])->name('leads.activities');
    Route::post('/leads/{lead}/transfers', [CrmLeadController::class, 'transfer'])->name('leads.transfers');
    Route::post('/leads/{lead}/convert', [CrmLeadController::class, 'convert'])->name('leads.convert');
    Route::get('/taxonomy', [CrmLeadController::class, 'taxonomy'])->name('taxonomy.index');
    Route::post('/taxonomy', [CrmLeadController::class, 'saveTaxonomy'])->name('taxonomy.store');
    Route::get('/recipients', [CrmLeadController::class, 'recipients'])->name('recipients.index');
    Route::get('/transfers/inbox', [CrmLeadController::class, 'inbox'])->name('transfers.inbox');
    Route::post('/transfers/{transfer}/response', [CrmLeadController::class, 'respondTransfer'])->name('transfers.response');
});

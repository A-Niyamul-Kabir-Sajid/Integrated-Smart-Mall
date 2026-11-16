<?php

use App\Modules\ChatbotRag\Http\Controllers\AdminAssistantController;
use App\Modules\ChatbotRag\Http\Controllers\AssistantHandoffController;
use App\Modules\ChatbotRag\Http\Controllers\CustomerAssistantController;
use App\Modules\ChatbotRag\Http\Controllers\KnowledgeAdminController;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use Illuminate\Support\Facades\Route;

// The visitors' assistant of a mall is public: guests and signed-in visitors may ask.
Route::prefix('malls/{publicMall}/assistant')->name('assistant.')->group(function (): void {
    Route::get('/', [CustomerAssistantController::class, 'show'])->name('show');
    Route::post('/messages', [CustomerAssistantController::class, 'store'])->middleware('throttle:assistant')->name('messages.store');
    Route::delete('/', [CustomerAssistantController::class, 'destroy'])->name('destroy');
    Route::get('/go/{type}/{id}', [AssistantHandoffController::class, 'show'])
        ->whereIn('type', NavigationHandoff::TYPES)->whereUuid('id')->name('go');
});

// The administrators' assistant, its activity page and its knowledge of one mall: administrators of
// that mall (or the platform) only. This check is what makes a conversation an administrators' one.
Route::middleware(['auth', 'throttle:assistant-admin', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}/assistant')
    ->name('assistant.admin.')
    ->group(function (): void {
        Route::get('/', [AdminAssistantController::class, 'show'])->name('show');
        Route::post('/messages', [AdminAssistantController::class, 'store'])->name('messages.store');
        Route::delete('/', [AdminAssistantController::class, 'destroy'])->name('destroy');
        Route::get('/activity', [AdminAssistantController::class, 'activity'])->name('activity');

        Route::prefix('knowledge')->name('knowledge.')->group(function (): void {
            Route::get('/', [KnowledgeAdminController::class, 'index'])->name('index');
            Route::get('/create', [KnowledgeAdminController::class, 'create'])->name('create');
            Route::post('/', [KnowledgeAdminController::class, 'store'])->name('store');
            Route::post('/sync', [KnowledgeAdminController::class, 'sync'])->name('sync');
            Route::get('/{knowledgeDocument}', [KnowledgeAdminController::class, 'show'])->name('show');
            Route::get('/{knowledgeDocument}/edit', [KnowledgeAdminController::class, 'edit'])->name('edit');
            Route::put('/{knowledgeDocument}', [KnowledgeAdminController::class, 'update'])->name('update');
            Route::post('/{knowledgeDocument}/withdraw', [KnowledgeAdminController::class, 'withdraw'])->name('withdraw');
            Route::post('/{knowledgeDocument}/restore', [KnowledgeAdminController::class, 'restore'])->name('restore');
            Route::delete('/{knowledgeDocument}', [KnowledgeAdminController::class, 'destroy'])->name('destroy');
        });
    });

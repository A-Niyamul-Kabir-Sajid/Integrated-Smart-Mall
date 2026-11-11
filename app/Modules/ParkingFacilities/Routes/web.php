<?php

use App\Modules\ParkingFacilities\Http\Controllers\FacilityAdminController;
use Illuminate\Support\Facades\Route;

// Facility maintenance of one mall: only administrators of that mall (or the platform) may enter.
Route::middleware(['auth', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}/facilities')
    ->name('facilities.admin.')
    ->group(function (): void {
        Route::get('/', [FacilityAdminController::class, 'index'])->name('index');
        Route::get('/create', [FacilityAdminController::class, 'create'])->name('create');
        Route::post('/', [FacilityAdminController::class, 'store'])->name('store');
        Route::get('/{facility}/edit', [FacilityAdminController::class, 'edit'])->whereUuid('facility')->name('edit');
        Route::put('/{facility}', [FacilityAdminController::class, 'update'])->whereUuid('facility')->name('update');
    });

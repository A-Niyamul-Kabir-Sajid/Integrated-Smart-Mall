<?php

use App\Modules\ParkingFacilities\Http\Controllers\FacilityAdminController;
use App\Modules\ParkingFacilities\Http\Controllers\ParkingController;
use App\Modules\ParkingFacilities\Http\Controllers\ParkingZoneAdminController;
use Illuminate\Support\Facades\Route;

// Saving or clearing one's own parking spot needs a signed-in account.
Route::middleware(['auth', 'throttle:parking'])->group(function (): void {
    Route::post('/malls/{publicMall}/parking', [ParkingController::class, 'store'])->name('parking.store');
    Route::delete('/malls/{publicMall}/parking', [ParkingController::class, 'destroy'])->name('parking.destroy');
});

// Facility and parking-zone maintenance of one mall: only administrators of that mall (or the platform) may enter.
Route::middleware(['auth', 'can:administer-mall,mall'])
    ->prefix('admin/malls/{mall}')
    ->group(function (): void {
        Route::prefix('facilities')->name('facilities.admin.')->group(function (): void {
            Route::get('/', [FacilityAdminController::class, 'index'])->name('index');
            Route::get('/create', [FacilityAdminController::class, 'create'])->name('create');
            Route::post('/', [FacilityAdminController::class, 'store'])->name('store');
            Route::get('/{facility}/edit', [FacilityAdminController::class, 'edit'])->whereUuid('facility')->name('edit');
            Route::put('/{facility}', [FacilityAdminController::class, 'update'])->whereUuid('facility')->name('update');
        });

        Route::prefix('parking-zones')->name('parking-zones.admin.')->group(function (): void {
            Route::get('/create', [ParkingZoneAdminController::class, 'create'])->name('create');
            Route::post('/', [ParkingZoneAdminController::class, 'store'])->name('store');
            Route::get('/{zone}/edit', [ParkingZoneAdminController::class, 'edit'])->whereUuid('zone')->name('edit');
            Route::put('/{zone}', [ParkingZoneAdminController::class, 'update'])->whereUuid('zone')->name('update');
        });
    });

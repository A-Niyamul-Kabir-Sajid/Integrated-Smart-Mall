<?php

use App\Modules\AccountsAccess\Http\Controllers\AccountController;
use App\Modules\AccountsAccess\Http\Controllers\PasswordController;
use App\Modules\AccountsAccess\Http\Controllers\ProfileController;
use App\Modules\AccountsAccess\Http\Controllers\RegisterController;
use App\Modules\AccountsAccess\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:password-change')
        ->name('account.password.update');
});

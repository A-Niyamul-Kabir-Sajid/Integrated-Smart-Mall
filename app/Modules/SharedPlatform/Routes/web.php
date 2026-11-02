<?php

use App\Modules\SharedPlatform\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

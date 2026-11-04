<?php

use Illuminate\Support\Facades\Route;

Route::get('/module-fixture', fn () => ['source' => 'api fixture'])->name('module-fixture.api');

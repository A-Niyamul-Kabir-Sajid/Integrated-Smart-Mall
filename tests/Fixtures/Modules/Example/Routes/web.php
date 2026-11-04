<?php

use Illuminate\Support\Facades\Route;

Route::get('/_module-fixture', fn () => 'web fixture')->name('module-fixture.web');

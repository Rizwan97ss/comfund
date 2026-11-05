<?php

use Illuminate\Support\Facades\Route;

// Fortify needs these names to build redirects and email links. All of them render the SPA.
Route::view('/login', 'app')->name('login');
Route::view('/reset-password/{token}', 'app')->name('password.reset');
Route::view('/email/verify', 'app')->name('verification.notice');

Route::view('/{any?}', 'app')->where('any', '^(?!api/|sanctum/|up$|build/).*$')->name('spa');

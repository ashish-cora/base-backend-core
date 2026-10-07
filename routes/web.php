<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authenticated landing: Fortify redirects logins here. Redirects to the
// welcome page until a dashboard Blade screen is built (no views yet).
    Route::get('/home', function () {
        return redirect('/');
    })->middleware('auth')->name('home');

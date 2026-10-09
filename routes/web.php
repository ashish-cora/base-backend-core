<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authenticated landing: Fortify redirects logins here.
// Base ships a minimal stub (resources/views/home.blade.php, CHILD: override).
Route::get('/home', function () {
    return view('home');
})->middleware('auth')->name('home');

// Auth-gated account pages: forms POST directly to Fortify routes
// (no app controller needed — Fortify returns back()->with('status') / withErrors()).
Route::middleware('auth')->group(function () {
    Route::view('/profile/password', 'profile.password')->name('profile.password');
    Route::view('/profile/two-factor', 'profile.two-factor')->name('profile.two-factor');
});

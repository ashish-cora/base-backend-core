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

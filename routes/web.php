<?php

use App\Http\Controllers\Profile\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authenticated landing: Fortify redirects logins here.
// Base ships a minimal stub (resources/views/home.blade.php, CHILD: override).
Route::get('/home', function () {
    return view('home');
})->middleware('auth')->name('home');

// Auth-gated account pages. Password change posts directly to Fortify;
// 2FA enable/disable goes through our wrapper so password confirmation
// and the toggle complete in one POST (no confirm-password redirect loop).
Route::middleware('auth')->group(function () {
    Route::view('/profile/password', 'profile.password')->name('profile.password');
    Route::view('/profile/two-factor', 'profile.two-factor')->name('profile.two-factor');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'store'])->name('profile.two-factor.store');
    Route::delete('/profile/two-factor', [TwoFactorController::class, 'destroy'])->name('profile.two-factor.destroy');
});

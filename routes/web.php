<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

// Auth Routes (Login, Register, Logout)
Auth::routes();

// Protected Routes (Hanya bisa diakses setelah login)
Route::middleware('auth')->group(function () {

    // Dashboard Utama
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Redirect /home ke dashboard
    Route::get('/home', function () {
        return redirect('/dashboard');
    })->name('home');
});

// Optional: Logout route (sudah ada di Auth::routes(), tapi bisa di-custom)
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout');
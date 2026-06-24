<?php

use Illuminate\Support\Facades\Auth; 

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Kita ganti pakai Auth:: besar agar teks editor paham
    if (Auth::check()) {
        return Auth::user()->role == 'admin' ? redirect('/admin') : redirect('/user');
    }
    return redirect('/login');
});

// Kelompok Rute untuk yang BELUM login (Tamu)
Route::middleware('guest')->group(function(){
    // Kita ubah url-nya menjadi /login dan beri ->name('login')
    Route::get('/login', [AuthController::class, 'masuk'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login']);
    
    Route::get('/register', [AuthController::class, 'daftar']);
    Route::post('/daftar', [AuthController::class, 'register']);
});

// Kelompok Rute untuk yang SUDAH login
Route::middleware('auth')->group(function (){
    // Bagian admin
    Route::get('/admin', [AdminController::class, 'index']);
    // Bagian user
    Route::get('/user', [UsersController::class, 'index']);
    Route::get('/logout', [AuthController::class, 'logout']);
});
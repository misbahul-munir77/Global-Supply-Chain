<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ApiController;   // Controller API baru kita
use Illuminate\Support\Facades\Route;

// ============================================================
// RUTE UTAMA (/)
// Redirect otomatis: admin ke /admin, user ke /user, tamu ke /login
// ============================================================
Route::get('/', function () {
    if (Auth::check()) {
        return Auth::user()->role == 'admin' ? redirect('/admin') : redirect('/user');
    }
    return redirect('/login');
});

// ============================================================
// RUTE UNTUK TAMU (Belum login)
// middleware('guest') → jika sudah login, tidak bisa akses halaman ini
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'masuk'])->name('login');
    Route::post('/masuk',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'daftar']);
    Route::post('/daftar',  [AuthController::class, 'register']);
});

// ============================================================
// RUTE UNTUK USER YANG SUDAH LOGIN
// middleware('auth') → jika belum login, otomatis diarahkan ke /login
// ============================================================
Route::middleware('auth')->group(function () {

    // --- Halaman Dashboard ---
    Route::get('/admin', [AdminController::class, 'index']);
    Route::get('/user',  [UsersController::class, 'index']);
    Route::get('/logout',[AuthController::class,  'logout']);

    // ============================================================
    // RUTE API (dipanggil via AJAX dari JavaScript dashboard)
    //
    // Semua rute di bawah diakses dengan:
    //   $.ajax({ url: '/api/...' }) atau fetch('/api/...')
    //
    // Hasilnya adalah JSON yang langsung dipakai oleh Chart.js & Leaflet
    // ============================================================

    // 1. Cuaca: /api/weather?lat=-6.2&lng=106.8
    //    Mengembalikan prakiraan 7 hari dari OpenMeteo API
    Route::get('/api/weather', [ApiController::class, 'getWeather']);

    // 2. Ekonomi: /api/economy/ID  (ID = kode negara, bisa diganti SG, CN, dll)
    //    Mengembalikan GDP, inflasi, populasi, ekspor, impor dari World Bank
    Route::get('/api/economy/{country}', [ApiController::class, 'getEconomy']);

    // 3. Daftar Negara: /api/countries
    //    Mengembalikan semua negara (nama, bendera, koordinat, mata uang)
    Route::get('/api/countries', [ApiController::class, 'getCountries']);

    // 4. Kurs Mata Uang: /api/exchange
    //    Mengembalikan kurs IDR terhadap mata uang utama dunia
    Route::get('/api/exchange', [ApiController::class, 'getExchangeRate']);

    // 5. Data Pelabuhan: /api/ports
    //    Mengembalikan daftar pelabuhan utama dengan koordinat & status
    Route::get('/api/ports', [ApiController::class, 'getPorts']);

    // 6. Berita: /api/news?category=logistics
    //    Mengembalikan berita ekonomi, logistik, geopolitik
    Route::get('/api/news', [ApiController::class, 'getNews']);

    // 7. Skor Risiko: /api/risk/ID
    //    Mengembalikan skor risiko 0-100 beserta breakdown per kategori
    Route::get('/api/risk/{country}', [ApiController::class, 'getRiskScore']);
});
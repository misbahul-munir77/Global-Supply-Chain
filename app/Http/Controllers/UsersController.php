<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    public function index(){
        // Baik user biasa maupun admin dapat melihat halaman monitoring utama
        return view('user.dashboard');
    }
}

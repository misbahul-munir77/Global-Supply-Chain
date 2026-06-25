<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    public function index(){
        if (Auth::user()->role == 'user') {
            return view('user.dashboard');
        }
        else{
            return back();
        }
    }
}

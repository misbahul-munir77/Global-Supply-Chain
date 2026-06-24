<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // login
    public function masuk(){
        return view('login.login');
    }

    public function login (Request $request){
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ],
        [
            'email.required' => 'email wajib diisi',
            'password.required' => 'password wajib diisi'
        ]);

        $infoLogin = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if(Auth::attempt($infoLogin)){
            $request->session()->regenerate();

            $user = Auth::user();
            if($user->role == 'admin'){
                return redirect('/admin');
            }
            else{
                return redirect('/user');
            }
        }
        else{
            return redirect('/login')->withErrors(['login' => 'email atau password yang anda masukkan salah'])->withInput();
        }
    }

    // bagian daftar
    public function daftar() {
        return view('login.register');
    }


    public function register(Request $request){
        $request->validate(
            [
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
                'confirm_password' => 'required|same:password'
            ],
            [
                'name.required' => 'nama wajib di isi',
                'email.required' => 'email wajib di isi',
                'password.required' => 'password wajib di isi',
                'confirm_password.required' => 'mohon isi untuk konfirmasi password',
                'confirm_password.same' => 'password tidak sama'
            ]);

            DB::table('users')->insert([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return redirect('/login');
    }

    public function logout(){
        Auth::logout();
        return redirect('/login');
    }
}

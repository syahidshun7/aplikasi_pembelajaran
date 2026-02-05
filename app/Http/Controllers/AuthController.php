<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
     public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        

        if ($email === 'admin' && $password === '123') {
            return redirect('/dashboard')
                ->with('success', 'Login berhasil');
        }

        return back()->with('error', 'Email atau password salah');
    }

}
$this->call(ProjectSeeder::class);




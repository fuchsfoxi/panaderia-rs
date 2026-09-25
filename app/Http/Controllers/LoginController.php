<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credenciales = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

    }
}
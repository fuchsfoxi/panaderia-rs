<?php

namespace App\Http\Controllers;


use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
            $credentials = $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);

    }



}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class HistorialController extends Controller
{
    //
    public function index()
    {
        return view('history.index');    
    }
}

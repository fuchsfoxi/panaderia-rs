<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class ProduccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
        public function index()
        {
            return view('produccion.index');
        }

        public function store(Request $request)
        {
            // pendiente, se hace al final del sprint
        }
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

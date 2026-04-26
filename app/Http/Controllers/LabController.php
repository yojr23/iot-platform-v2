<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $labs = Lab::all();
        return view('labs.create', compact('labs'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'process_line' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Lab::create($validatedData);

        return redirect()->route('labs.create')->with('success', 'Laboratorio creado correctamente.');
    }

    public function edit(Lab $lab)
    {
        return view('labs.edit', compact('lab'));
    }

    public function update(Request $request, Lab $lab)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'process_line' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $lab->update($validatedData);

        return redirect()->route('labs.create')->with('success', 'Laboratorio actualizado correctamente.');
    }

    public function destroy(Lab $lab)
    {
        $lab->delete();
        return redirect()->route('labs.create')->with('success', 'Laboratorio eliminado correctamente.');
    }
}

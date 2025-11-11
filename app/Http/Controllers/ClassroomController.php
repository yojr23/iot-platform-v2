<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $classrooms = Classroom::all();
        return view('classrooms.create', compact('classrooms'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'building' => 'required|string|max:10',
            'floor' => 'required|integer|min:0',
            'capacity' => 'required|integer|min:1',
        ]);

        Classroom::create($validatedData);

        return redirect()->route('classrooms.create')->with('success', 'Ubicación de aula creada correctamente.');
    }

    public function edit(Classroom $classroom)
    {
        return view('classrooms.edit', compact('classroom'));
    }

    public function update(Request $request, Classroom $classroom)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'building' => 'required|string|max:10',
            'floor' => 'required|integer|min:0',
            'capacity' => 'required|integer|min:1',
        ]);

        $classroom->update($validatedData);

        return redirect()->route('classrooms.create')->with('success', 'Ubicación de aula actualizada correctamente.');
    }

    public function destroy(Classroom $classroom)
    {
        $classroom->delete();
        return redirect()->route('classrooms.create')->with('success', 'Ubicación de aula eliminada correctamente.');
    }
}

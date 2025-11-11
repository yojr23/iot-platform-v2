@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Crear Ubicación de Aula</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('classrooms.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Nombre del Aula</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="building">Edificio</label>
                    <input type="text" name="building" id="building" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="floor">Piso</label>
                    <input type="number" name="floor" id="floor" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label for="capacity">Capacidad</label>
                    <input type="number" name="capacity" id="capacity" class="form-control" required min="1">
                </div>
                <button type="submit" class="btn btn-primary mt-3">Guardar</button>
            </form>

            <div class="mt-4">
                <h5>Ubicaciones de Aulas Existentes</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Edificio</th>
                            <th>Piso</th>
                            <th>Capacidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classrooms as $classroom)
                            <tr>
                                <td>{{ $classroom->name }}</td>
                                <td>{{ $classroom->building }}</td>
                                <td>{{ $classroom->floor }}</td>
                                <td>{{ $classroom->capacity }}</td>
                                <td>
                                    <a href="{{ route('classrooms.edit', $classroom->id) }}" class="btn btn-warning btn-sm">Editar</a>
                                    <form action="{{ route('classrooms.destroy', $classroom->id) }}" method="POST" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

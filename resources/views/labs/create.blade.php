@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Crear Laboratorio</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('labs.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Nombre del Laboratorio</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="area">Area</label>
                    <input type="text" name="area" id="area" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="process_line">Linea de Proceso</label>
                    <input type="text" name="process_line" id="process_line" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Descripcion</label>
                    <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Guardar</button>
            </form>

            <div class="mt-4">
                <h5>Laboratorios Existentes</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Area</th>
                            <th>Linea de Proceso</th>
                            <th>Descripcion</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($labs as $lab)
                            <tr>
                                <td>{{ $lab->name }}</td>
                                <td>{{ $lab->area }}</td>
                                <td>{{ $lab->process_line }}</td>
                                <td>{{ $lab->description ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('labs.edit', $lab->id) }}" class="btn btn-warning btn-sm">Editar</a>
                                    <form action="{{ route('labs.destroy', $lab->id) }}" method="POST" style="display:inline-block;">
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

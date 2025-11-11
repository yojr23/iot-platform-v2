@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Editar Ubicación de Aula</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('classrooms.update', $classroom->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Nombre del Aula</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ $classroom->name }}" required>
                </div>
                <div class="form-group">
                    <label for="building">Edificio</label>
                    <input type="text" name="building" id="building" class="form-control" value="{{ $classroom->building }}" required>
                </div>
                <div class="form-group">
                    <label for="floor">Piso</label>
                    <input type="number" name="floor" id="floor" class="form-control" value="{{ $classroom->floor }}" required min="0">
                </div>
                <div class="form-group">
                    <label for="capacity">Capacidad</label>
                    <input type="number" name="capacity" id="capacity" class="form-control" value="{{ $classroom->capacity }}" required min="1">
                </div>
                <button type="submit" class="btn btn-primary mt-3">Actualizar</button>
            </form>
        </div>
    </div>
</div>
@endsection

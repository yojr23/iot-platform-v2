@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Editar Laboratorio</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('labs.update', $lab->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Nombre del Laboratorio</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ $lab->name }}" required>
                </div>
                <div class="form-group">
                    <label for="area">Area</label>
                    <input type="text" name="area" id="area" class="form-control" value="{{ $lab->area }}" required>
                </div>
                <div class="form-group">
                    <label for="process_line">Linea de Proceso</label>
                    <input type="text" name="process_line" id="process_line" class="form-control" value="{{ $lab->process_line }}" required>
                </div>
                <div class="form-group">
                    <label for="description">Descripcion</label>
                    <textarea name="description" id="description" class="form-control" rows="2">{{ $lab->description }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Actualizar</button>
            </form>
        </div>
    </div>
</div>
@endsection

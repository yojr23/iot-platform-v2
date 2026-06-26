@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Editar Sensor</h1>
        <form action="{{ route('sensors.update', $sensor->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Nombre del Sensor -->
            <div class="mb-3">
                <label for="name" class="form-label">Nombre del Sensor</label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $sensor->name }}" required>
            </div>

            <!-- Rango Mínimo -->
            <div class="mb-3">
                <label for="min_range" class="form-label">Rango Mínimo</label>
                <input type="number" step="0.01" class="form-control" id="min_range" name="min_range" value="{{ $sensor->sensorType->min_range }}" required>
            </div>

            <!-- Rango Máximo -->
            <div class="mb-3">
                <label for="max_range" class="form-label">Rango Máximo</label>
                <input type="number" step="0.01" class="form-control" id="max_range" name="max_range" value="{{ $sensor->sensorType->max_range }}" required>
            </div>

            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
@endsection
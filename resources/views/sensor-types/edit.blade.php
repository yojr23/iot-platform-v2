@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Editar Tipo de Sensor</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('sensor-types.update', $sensorType->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Nombre</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ $sensorType->name }}" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unidad</label>
                    <input type="text" name="unit" id="unit" class="form-control" value="{{ $sensorType->unit }}" required>
                </div>
                <div class="form-group">
                    <label for="min_range">Rango Mínimo</label>
                    <input type="number" name="min_range" id="min_range" class="form-control" value="{{ $sensorType->min_range }}" required>
                </div>
                <div class="form-group">
                    <label for="max_range">Rango Máximo</label>
                    <input type="number" name="max_range" id="max_range" class="form-control" value="{{ $sensorType->max_range }}" required>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Actualizar</button>
            </form>
        </div>
    </div>
</div>
@endsection

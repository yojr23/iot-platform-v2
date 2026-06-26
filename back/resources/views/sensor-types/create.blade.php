@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h5>Crear Tipo de Sensor</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <form action="{{ route('sensor-types.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Nombre</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unidad</label>
                    <input type="text" name="unit" id="unit" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="min_range">Rango Mínimo</label>
                    <input type="number" name="min_range" id="min_range" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="max_range">Rango Máximo</label>
                    <input type="number" name="max_range" id="max_range" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Guardar</button>
            </form>

            <div class="mt-4">
                <h5>Tipos de Sensores Existentes</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Unidad</th>
                            <th>Rango Mínimo</th>
                            <th>Rango Máximo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sensorTypes as $sensorType)
                            <tr>
                                <td>{{ $sensorType->name }}</td>
                                <td>{{ $sensorType->unit }}</td>
                                <td>{{ $sensorType->min_range }}</td>
                                <td>{{ $sensorType->max_range }}</td>
                                <td>
                                    <a href="{{ route('sensor-types.edit', $sensorType->id) }}" class="btn btn-warning btn-sm">Editar</a>
                                    <form action="{{ route('sensor-types.destroy', $sensorType->id) }}" method="POST" style="display:inline-block;">
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

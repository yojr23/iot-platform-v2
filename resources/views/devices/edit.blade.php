@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-microchip me-2"></i> Editar Dispositivo
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ route('devices.update', $device->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <!-- Nombre del Dispositivo -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" placeholder=" " 
                               value="{{ old('name', $device->name) }}" required>
                        <label for="name">Nombre del Dispositivo</label>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Dirección IP -->
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('ip_address') is-invalid @enderror" 
                               id="ip_address" name="ip_address" placeholder=" " 
                               value="{{ old('ip_address', $device->ip_address) }}">
                        <label for="ip_address">Dirección IP</label>
                        @error('ip_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Dirección MAC -->
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('mac_address') is-invalid @enderror" 
                               id="mac_address" name="mac_address" placeholder=" " 
                               value="{{ old('mac_address', $device->mac_address) }}">
                        <label for="mac_address">Dirección MAC</label>
                        @error('mac_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <select class="form-select @error('classroom_id') is-invalid @enderror" 
                                id="classroom_id" name="classroom_id" required>
                            <option value="" disabled selected>Seleccione una ubicación</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" 
                                    {{ old('classroom_id', $device->classroom_id) == $classroom->id ? 'selected' : '' }}>
                                    {{ $classroom->name }} (Edificio {{ $classroom->building }}, Piso {{ $classroom->floor }})
                                </option>
                            @endforeach
                        </select>
                        <label for="classroom_id">Ubicación (Aula)</label>
                        @error('classroom_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="{{ route('devices.index') }}" class="btn btn-secondary me-md-2">
                    <i class="fas fa-times-circle me-1"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-microchip me-2"></i> {{ isset($device) ? 'Editar Dispositivo' : 'Nuevo Dispositivo' }}
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ isset($device) ? route('devices.update', $device->id) : route('devices.store') }}" method="POST">
            @csrf
            @if(isset($device))
                @method('PUT')
            @endif

            <div class="row g-3">
                <!-- Nombre del Dispositivo -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" placeholder=" " 
                               value="{{ old('name', $device->name ?? '') }}" required>
                        <label for="name">Nombre del Dispositivo</label>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Número de Serie -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('serial_number') is-invalid @enderror" 
                               id="serial_number" name="serial_number" placeholder=" " 
                               value="{{ old('serial_number', $device->serial_number ?? '') }}" required>
                        <label for="serial_number">Número de Serie</label>
                        @error('serial_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Tipo de Dispositivo -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <select class="form-select @error('device_type_id') is-invalid @enderror" 
                                id="device_type_id" name="device_type_id" required>
                            <option value="" disabled selected>Seleccione un tipo</option>
                            @foreach ($deviceTypes as $type)
                                <option value="{{ $type->id }}" 
                                    {{ old('device_type_id', $device->device_type_id ?? '') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        <label for="device_type_id">Tipo de Dispositivo</label>
                        @error('device_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Ubicación (Aula) -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <select class="form-select @error('classroom_id') is-invalid @enderror" 
                                id="classroom_id" name="classroom_id" required>
                            <option value="" disabled selected>Seleccione una ubicación</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" 
                                    {{ old('classroom_id', $device->classroom_id ?? '') == $classroom->id ? 'selected' : '' }}>
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

                <!-- Dirección IP -->
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('ip_address') is-invalid @enderror" 
                               id="ip_address" name="ip_address" placeholder=" " 
                               value="{{ old('ip_address', $device->ip_address ?? '') }}">
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
                               value="{{ old('mac_address', $device->mac_address ?? '') }}">
                        <label for="mac_address">Dirección MAC</label>
                        @error('mac_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Estado -->
                <div class="col-md-4">
                    <div class="form-check form-switch pt-3">
                        <input class="form-check-input @error('status') is-invalid @enderror" 
                               type="checkbox" id="status" name="status" value="1"
                               {{ old('status', $device->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="status">Dispositivo Activo</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Botones -->
                <div class="col-12 mt-4">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('devices.index') }}" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times-circle me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> {{ isset($device) ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
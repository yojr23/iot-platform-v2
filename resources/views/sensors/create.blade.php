@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-thermometer-half me-2"></i> Registrar Nuevo Sensor
        </h5>
    </div>
    <div class="card-body">
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('sensors.store') }}" method="POST" id="sensorForm">
            @csrf
            
            <div class="row g-3">
                <!-- Nombre del Sensor -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" placeholder=" " 
                               value="{{ old('name') }}" required>
                        <label for="name">Nombre del Sensor</label>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <!-- Dispositivo -->
                <ddiv class="col-md-6">
                    <div class="form-floating">
                        <select class="form-select @error('device_id') is-invalid @enderror" 
                                id="device_id" name="device_id" required>
                            <option value="" disabled selected>Seleccione un dispositivo</option>
                            @forelse($devices as $device)
                                <option value="{{ $device->id }}" 
                                    {{ old('device_id') == $device->id ? 'selected' : '' }}>
                                    {{ $device->name }} ({{ $device->classroom->name ?? 'Sin aula' }})
                                </option>
                            @empty
                                <option value="" disabled>No hay dispositivos disponibles</option>
                            @endforelse
                        </select>
                        <label for="device_id">Dispositivo</label>
                        @error('device_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <!-- Debug: Mostrar dispositivos disponibles -->
                    @if($devices->isEmpty())
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No se encontraron dispositivos activos. 
                        <a href="{{ route('devices.create') }}" class="alert-link">
                            Crear un dispositivo primero
                        </a>
                    </div>
                    @endif
                </div>
                
                <!-- Tipo de Sensor -->
                <div class="col-md-6">
                    <div class="form-floating">
                        <select class="form-select @error('sensor_type_id') is-invalid @enderror" 
                                id="sensor_type_id" name="sensor_type_id" required>
                            <option value="" disabled selected>Seleccione un tipo</option>
                            @foreach($sensorTypes as $type)
                                <option value="{{ $type->id }}" 
                                    {{ old('sensor_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }} ({{ $type->unit }})
                                </option>
                            @endforeach
                        </select>
                        <label for="sensor_type_id">Tipo de Sensor</label>
                        @error('sensor_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <!-- Estado -->
                <div class="col-md-6">
                    <!-- Campo oculto para enviar 0 si el checkbox está desmarcado -->
                    <input type="hidden" name="status" value="0">
                    <div class="form-check form-switch mt-3 ps-5">
                        <input class="form-check-input" type="checkbox" 
                               id="status" name="status" value="1"
                               {{ old('status', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="status">Sensor Activo</label>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="col-12 mt-4">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('sensors.index') }}" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times-circle me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-1"></i> Guardar Sensor
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#sensorForm').on('submit', function() {
            // Deshabilitar el botón para evitar múltiples envíos
            $('#submitBtn').prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin me-1"></i> Guardando...');
        });
    });
</script>
@endpush
@endsection
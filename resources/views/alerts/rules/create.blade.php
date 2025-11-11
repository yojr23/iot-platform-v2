@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Configurar Reglas de Alerta</h5>
            <a href="{{ route('alerts.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        <div class="card-body">
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('alert-rules.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="sensor_type_id">Tipo de Sensor</label>
                    <select name="sensor_type_id" id="sensor_type_id" class="form-control" required>
                        <option value="">Seleccione un tipo de sensor</option>
                        @foreach($sensorTypes as $type)
                            <option value="{{ $type->id }}" data-min="{{ $type->min_range }}" data-max="{{ $type->max_range }}">
                                {{ $type->name }} ({{ $type->unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="min_value">Valor Mínimo</label>
                    <input type="number" step="0.01" class="form-control" id="min_value" name="min_value">
                    <small class="form-text text-muted">
                        Opcional. Se dispara cuando la lectura es menor a este valor.
                        Rango válido: <span id="min_range"></span>
                    </small>
                </div>

                <div class="form-group">
                    <label for="max_value">Valor Máximo</label>
                    <input type="number" step="0.01" class="form-control" id="max_value" name="max_value">
                    <small class="form-text text-muted">
                        Opcional. Se dispara cuando la lectura supera este valor.
                        Rango válido: <span id="max_range"></span>
                    </small>
                </div>

                <div class="form-group">
                    <label for="severity">Severidad</label>
                    <select name="severity" id="severity" class="form-control" required>
                        <option value="info">Información</option>
                        <option value="warning">Advertencia</option>
                        <option value="danger">Peligro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Mensaje de Alerta</label>
                    <textarea name="message" id="message" class="form-control" required></textarea>
                </div>

                <div class="form-group">
                    <label for="device_id">Dispositivo</label>
                    <select name="device_id" id="device_id" class="form-control" required>
                        <option value="">Seleccione un dispositivo</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->name }} ({{ $device->serial_number }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="sensor_id">Sensor</label>
                    <select name="sensor_id" id="sensor_id" class="form-control" required>
                        <option value="">Seleccione un sensor</option>
                        @foreach($sensors as $sensor)
                            <option value="{{ $sensor->id }}">{{ $sensor->name }} ({{ $sensor->device->name }})</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Guardar Regla</button>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Reglas de Alerta Existentes</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Sensor</th>
                            <th>Tipo de Sensor</th>
                            <th>Rango</th>
                            <th>Severidad</th>
                            <th>Mensaje</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alertRules as $rule)
                        <tr>
                            <td>{{ optional($rule->device)->name ?? 'N/D' }}</td>
                            <td>{{ optional($rule->sensor)->name ?? 'N/D' }}</td>
                            <td>{{ $rule->sensorType->name }}</td>
                            @php
                                $minText = is_null($rule->min_value) ? '—' : $rule->min_value;
                                $maxText = is_null($rule->max_value) ? '—' : $rule->max_value;
                            @endphp
                            <td>{{ $minText }} - {{ $maxText }} {{ $rule->sensorType->unit }}</td>
                            <td>
                                <span class="badge badge-{{ $rule->severity }}">
                                    {{ ucfirst($rule->severity) }}
                                </span>
                            </td>
                            <td>{{ $rule->message }}</td>
                            <td>
                                <form action="{{ route('alert-rules.destroy', $rule) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

@push('scripts')
<script>
document.getElementById('sensor_type_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const minRange = selected.dataset.min;
    const maxRange = selected.dataset.max;
    
    document.getElementById('min_range').textContent = `${minRange} - ${maxRange}`;
    document.getElementById('max_range').textContent = `${minRange} - ${maxRange}`;
    
    document.getElementById('min_value').min = minRange;
    document.getElementById('min_value').max = maxRange;
    document.getElementById('max_value').min = minRange;
    document.getElementById('max_value').max = maxRange;
});
</script>
@endpush
@endsection

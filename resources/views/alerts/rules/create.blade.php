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
                    <label for="name">Nombre de la Regla (opcional)</label>
                    <input type="text" name="name" id="name" class="form-control" maxlength="255">
                    <small class="form-text text-muted">
                        Útil para identificar la regla en listados y reportes.
                    </small>
                </div>

                <div class="form-group">
                    <label for="device_id">Dispositivo</label>
                    <select name="device_id" id="device_id" class="form-control">
                        <option value="">Todos los dispositivos (cualquier dispositivo)</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->name }} ({{ $device->serial_number }})</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Opcional. Selecciona un dispositivo para aplicar la regla a todos sus sensores del tipo elegido.
                    </small>
                </div>

                <div class="form-group">
                    <label for="sensor_id">Sensor</label>
                    <select name="sensor_id" id="sensor_id" class="form-control">
                        <option value="">Todos los sensores (del alcance seleccionado)</option>
                        @foreach($sensors as $sensor)
                            <option value="{{ $sensor->id }}"
                                data-device-id="{{ $sensor->device_id }}"
                                data-sensor-type-id="{{ $sensor->sensor_type_id }}">
                                {{ $sensor->name }} ({{ $sensor->device->name }})
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Opcional. Si seleccionas un sensor, la regla solo aplica a ese sensor.
                    </small>
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
                            <th>Nombre</th>
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
                            <td>{{ $rule->name ?? '—' }}</td>
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
const sensorTypeSelect = document.getElementById('sensor_type_id');
const deviceSelect = document.getElementById('device_id');
const sensorSelect = document.getElementById('sensor_id');
const sensorOptions = Array.from(sensorSelect.options).filter(option => option.value !== '');

sensorTypeSelect.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const minRange = selected.dataset.min;
    const maxRange = selected.dataset.max;

    document.getElementById('min_range').textContent = `${minRange} - ${maxRange}`;
    document.getElementById('max_range').textContent = `${minRange} - ${maxRange}`;

    document.getElementById('min_value').min = minRange;
    document.getElementById('min_value').max = maxRange;
    document.getElementById('max_value').min = minRange;
    document.getElementById('max_value').max = maxRange;

    syncSensorOptions();
});

function syncSensorOptions() {
    const deviceId = deviceSelect.value;
    const sensorTypeId = sensorTypeSelect.value;

    sensorOptions.forEach(option => {
        const matchesDevice = !deviceId || option.dataset.deviceId === deviceId;
        const matchesType = !sensorTypeId || option.dataset.sensorTypeId === sensorTypeId;
        const matches = matchesDevice && matchesType;
        option.hidden = !matches;
        option.disabled = !matches;
    });

    const selected = sensorSelect.selectedOptions[0];
    if (selected && selected.value && selected.disabled) {
        sensorSelect.value = '';
    }
}

deviceSelect.addEventListener('change', function () {
    syncSensorOptions();
});

sensorSelect.addEventListener('change', function () {
    const selected = sensorSelect.selectedOptions[0];

    if (!selected || !selected.value) {
        return;
    }

    const deviceId = selected.dataset.deviceId;
    const sensorTypeId = selected.dataset.sensorTypeId;

    if (deviceId && !deviceSelect.value) {
        deviceSelect.value = deviceId;
    }

    if (sensorTypeId && sensorTypeSelect.value !== sensorTypeId) {
        sensorTypeSelect.value = sensorTypeId;
        sensorTypeSelect.dispatchEvent(new Event('change'));
    }

    syncSensorOptions();
});

syncSensorOptions();
</script>
@endpush
@endsection

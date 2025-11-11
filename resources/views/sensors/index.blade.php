@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->user()?->is_admin ?? false;
@endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado de Sensores</h5>
        @if($isAdmin)
        <a href="{{ route('sensors.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Sensor
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="sensorsTable">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Dispositivo</th>
                        <th>Aula</th>
                        <th>Estado</th>
                        <th>Última Lectura</th>
                        <th>Fecha y Hora</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sensors as $sensor)
                    <tr data-sensor-id="{{ $sensor->id }}">
                        <td>{{ $sensor->name }}</td>
                        <td>{{ $sensor->sensorType->name }}</td>
                        <td>{{ $sensor->device->name }}</td>
                        <td>{{ $sensor->device->classroom->name }}</td>
                        <td>
                            <span class="badge bg-{{ $sensor->status ? 'success' : 'danger' }}">
                                {{ $sensor->status ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="latest-reading">
                            @if($sensor->readings->count() > 0)
                                {{ $sensor->readings->first()->value }} {{ $sensor->sensorType->unit }}
                            @else
                                Sin datos
                            @endif
                        </td>
                        <td class="reading-timestamp">
                            @if($sensor->readings->count() > 0)
                                {{ $sensor->readings->first()->created_at->format('d/m/Y H:i') }}
                            @else
                                Sin datos
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('sensors.show', $sensor) }}" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if($isAdmin)
                                <a href="{{ route('sensors.edit', $sensor) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('sensors.destroy', $sensor) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Pusher for real-time updates
    const pusher = new Pusher('{{ env('PUSHER_APP_KEY') }}', {
        cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
        forceTLS: true
    });

    // Function to update sensor reading in the table
    function updateSensorReading(sensorId, data) {
        const row = document.querySelector(`tr[data-sensor-id="${sensorId}"]`);
        if (!row) return;

        const latestReadingCell = row.querySelector('.latest-reading');
        const timestampCell = row.querySelector('.reading-timestamp');

        if (latestReadingCell && timestampCell) {
            latestReadingCell.textContent = `${data.value} ${data.unit}`;
            timestampCell.textContent = new Date(data.reading_time).toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    // Subscribe to all sensor channels
    @foreach($sensors as $sensor)
        const channel{{ $sensor->id }} = pusher.subscribe('sensor.{{ $sensor->id }}');
        channel{{ $sensor->id }}.bind('App\\Events\\NewSensorReading', function(data) {
            updateSensorReading({{ $sensor->id }}, data);
        });
    @endforeach
});
</script>
@endpush

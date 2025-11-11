@extends('layouts.app')

@section('content')
<div class="container">
    @if(!$activeAlerts->isEmpty())
        <div class="text-end mb-3">
            <form action="/alerts/mark-all-resolved" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">
                    Marcar todas como revisadas
                </button>
            </form>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    <h1 class="mb-3">Alertas Activas</h1>

    @if($activeAlerts->isEmpty())
        <div class="alert alert-success">
            No hay alertas activas en este momento.
        </div>
    @else
        <div class="list-group">
            @foreach($activeAlerts as $alert)
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">Sensor: {{ $alert->sensorReading->sensor->name }}</h6>
                            <p class="mb-1">
                                {{ $alert->alertRule->message }}<br>
                                <small class="text-muted">
                                    Valor detectado: {{ $alert->sensorReading->value }} {{ $alert->sensorReading->sensor->sensorType->unit }}
                                    · Aula: {{ $alert->sensorReading->sensor->device->classroom->name }}
                                </small>
                            </p>
                            <small class="text-muted">
                                Registrada {{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}
                            </small>
                        </div>
                        <form action="{{ route('alerts.resolve', $alert) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                Marcar como revisada
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            {{ $activeAlerts->links() }}
        </div>
    @endif

    <div class="card mt-4">
        <div class="card-header">
            <h5>Historial de Alertas</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Sensor</th>
                        <th>Dispositivo</th>
                        <th>Regla</th>
                        <th>Fecha</th>
                        <th>Resuelta en</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alertHistory as $alert)
                        <tr>
                            <td>{{ $alert->sensorReading->sensor->name }}</td>
                            <td>{{ $alert->sensorReading->sensor->device->name }}</td>
                            <td>{{ $alert->alertRule->message }}</td>
                            <td>{{ $alert->created_at }}</td>
                            <td>{{ $alert->resolved_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Sin alertas resueltas aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $alertHistory->links() }}
        </div>
    </div>
</div>
@endsection

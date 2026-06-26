@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
        <h5 class="mb-0">Alertas No Resueltas</h5>
        <a href="{{ route('alerts.index') }}" class="btn btn-light">
            <i class="fas fa-list"></i> Ver todas
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mensaje</th>
                        <th>Sensor</th>
                        <th>Valor</th>
                        <th>Gravedad</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts as $alert)
                    <tr>
                        <td>{{ $alert->id }}</td>
                        <td>{{ $alert->alertRule->message }}</td>
                        <td>{{ $alert->sensorReading->sensor->name }}</td>
                        <td>
                            {{ $alert->sensorReading->value }} 
                            {{ $alert->sensorReading->sensor->sensorType->unit }}
                        </td>
                        <td>
                            <span class="badge badge-{{ 
                                $alert->alertRule->severity == 'danger' ? 'danger' : 
                                ($alert->alertRule->severity == 'warning' ? 'warning' : 'info') 
                            }}">
                                {{ ucfirst($alert->alertRule->severity) }}
                            </span>
                        </td>
                        <td>{{ $alert->created_at->diffForHumans() }}</td>
                        <td>
                            <form action="{{ route('alerts.resolve', $alert) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Marcar como resuelta">
                                    <i class="fas fa-check"></i> Resolver
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $alerts->links() }}
        </div>
    </div>
</div>
@endsection
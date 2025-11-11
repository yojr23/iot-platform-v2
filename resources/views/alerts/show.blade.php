@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Detalles de la Alerta</h1>
    <div class="card">
        <div class="card-header">
            <h5>Alerta ID: {{ $alert->id }}</h5>
        </div>
        <div class="card-body">
            <p><strong>Mensaje:</strong> {{ $alert->alertRule->message }}</p>
            <p><strong>Sensor:</strong> {{ $alert->sensorReading->sensor->name }}</p>
            <p><strong>Valor Detectado:</strong> {{ $alert->sensorReading->value }} {{ $alert->sensorReading->sensor->sensorType->unit }}</p>
            <p><strong>Gravedad:</strong> <span class="badge badge-{{ $alert->alertRule->severity == 'danger' ? 'danger' : ($alert->alertRule->severity == 'warning' ? 'warning' : 'info') }}">{{ ucfirst($alert->alertRule->severity) }}</span></p>
            <p><strong>Fecha:</strong> {{ $alert->created_at->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</div>
@endsection

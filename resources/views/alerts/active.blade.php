@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Alertas Activas</h1>
    <div class="list-group">
        @foreach($activeAlerts as $alert)
            <a href="#" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">Sensor: {{ $alert->sensorReading->sensor->name }}</h6>
                    <small>{{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}</small>
                </div>
                <p class="mb-1">Mensaje: {{ $alert->alertRule->message }}</p>
                <small>Valor detectado: {{ $alert->sensorReading->value }} {{ $alert->sensorReading->sensor->sensorType->unit }}</small>
                <small>Aula: {{ $alert->sensorReading->sensor->device->classroom->name }}</small>
            </a>
        @endforeach
    </div>
    {{ $activeAlerts->links() }}
</div>
@endsection

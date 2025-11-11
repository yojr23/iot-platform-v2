@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Reglas de Alerta</h5>
            <form method="GET" action="{{ route('alert-rules.index') }}">
                <select name="device_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Filtrar por dispositivo</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ request('device_id') == $device->id ? 'selected' : '' }}>
                            {{ $device->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>Sensor</th>
                        <th>Tipo de Sensor</th>
                        <th>Valor Mínimo</th>
                        <th>Valor Máximo</th>
                        <th>Severidad</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alertRules as $rule)
                        <tr>
                            <td>{{ optional($rule->device)->name ?? 'N/D' }}</td>
                            <td>{{ optional($rule->sensor)->name ?? 'N/D' }}</td>
                            <td>{{ $rule->sensorType->name }}</td>
                            <td>{{ $rule->min_value }}</td>
                            <td>{{ $rule->max_value }}</td>
                            <td>{{ $rule->severity }}</td>
                            <td>{{ $rule->message }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

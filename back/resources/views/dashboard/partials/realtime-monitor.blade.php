<div class="col-md-8">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Monitor de Sensores en Tiempo Real</h5>
            <div class="d-flex align-items-center">
                <div class="form-check form-switch me-3">
                    <input class="form-check-input" type="checkbox" id="realTimeToggle" checked>
                    <label class="form-check-label" for="realTimeToggle">Tiempo Real</label>
                </div>

                <select id="deviceSelect_main" class="form-select me-2 device-select" aria-label="Seleccione un dispositivo">
                    <option value="" disabled selected>Seleccione un dispositivo</option>
                    @forelse($devices as $device)
                        <option value="{{ $device->id }}">{{ $device->name }}</option>
                    @empty
                        <option value="" disabled>No hay dispositivos disponibles</option>
                    @endforelse
                </select>

                <select id="sensorSelect_main" class="form-select sensor-select" aria-label="Seleccione un sensor">
                    <option value="" disabled selected>Seleccione un sensor</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <canvas id="sensorsChart_main" class="sensor-chart" height="300"></canvas>
        </div>
    </div>
</div>

<div class="col-md-4">
    <div class="card alerts-card h-100" id="alertsCardContainer">
        <div class="card-header d-flex justify-content-between align-items-center alerts-toggle"
             role="button"
             data-bs-toggle="collapse"
             data-bs-target="#alertsCollapse"
             aria-expanded="true"
             aria-controls="alertsCollapse"
             data-tooltip="true"
             title="Haz clic para expandir o colapsar las alertas"
             style="cursor: pointer; flex-shrink: 0;">
            <h5 id="alertsHeader" class="mb-0">Últimas Alertas ({{ $summary['activeAlerts'] }})</h5>
            <i class="fas fa-chevron-up" id="alertsChevron"></i>
        </div>
        <div id="alertsCollapse" class="collapse show">
            <div class="card-body d-flex flex-column" style="flex: 1 1 auto; min-height: 0; overflow: hidden; padding: 0;">
                <div id="alertsList" class="alerts-scroll flex-grow-1" style="overflow-y: auto; overflow-x: hidden;">
                    @if(isset($activeAlertsList) && !$activeAlertsList->isEmpty())
                        <div class="list-group list-group-flush">
                            @foreach($activeAlertsList as $alert)
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Sensor: {{ $alert->sensorReading->sensor->name }}</h6>
                                        <small>{{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}</small>
                                    </div>
                                    <p class="mb-1">Mensaje: {{ $alert->alertRule->message }}</p>
                                    <small>Valor detectado: {{ $alert->sensorReading->value }} {{ $alert->sensorReading->sensor->sensorType->unit }}</small>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info m-3 mb-0">
                            No hay alertas recientes disponibles.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

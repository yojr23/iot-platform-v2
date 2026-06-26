@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">Métricas de API</h2>
            <small class="text-muted">Ventana de observación: últimos {{ $snapshot['window_minutes'] }} minutos</small>
        </div>
        <div class="text-end">
            <button id="refreshMetrics" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-rotate-right me-1"></i> Actualizar
            </button>
            <div class="small text-muted mt-1" id="metricsGeneratedAt">Carga inicial</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Requests totales</h6>
                    <div class="display-6" id="metricRequests">{{ $snapshot['requests_total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Errores totales</h6>
                    <div class="display-6 text-danger" id="metricErrors">{{ $snapshot['errors_total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Error rate (%)</h6>
                    <div class="display-6" id="metricErrorRate">{{ $snapshot['error_rate_percent'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Throughput (rpm)</h6>
                    <div class="display-6" id="metricThroughput">{{ $snapshot['throughput_rpm_avg'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Latencia promedio (ms)</h6>
                    <div class="display-5" id="metricAvgLatency">{{ $snapshot['avg_latency_ms'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">Serie por minuto</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Minuto</th>
                                    <th>Requests</th>
                                    <th>Errores</th>
                                    <th>Latencia promedio (ms)</th>
                                </tr>
                            </thead>
                            <tbody id="metricsSeriesBody">
                                @foreach($snapshot['series'] as $row)
                                    <tr>
                                        <td>{{ $row['bucket'] }}</td>
                                        <td>{{ $row['requests'] }}</td>
                                        <td>{{ $row['errors'] }}</td>
                                        <td>{{ $row['avg_latency_ms'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const refreshBtn = document.getElementById('refreshMetrics');
        const generatedAt = document.getElementById('metricsGeneratedAt');
        const requestsEl = document.getElementById('metricRequests');
        const errorsEl = document.getElementById('metricErrors');
        const errorRateEl = document.getElementById('metricErrorRate');
        const throughputEl = document.getElementById('metricThroughput');
        const avgLatencyEl = document.getElementById('metricAvgLatency');
        const seriesBody = document.getElementById('metricsSeriesBody');

        async function loadMetrics() {
            try {
                const response = await fetch('{{ route('metrics.data') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache',
                    },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const snapshot = payload?.snapshot ?? {};
                const series = Array.isArray(snapshot?.series) ? snapshot.series : [];

                requestsEl.textContent = snapshot.requests_total ?? 0;
                errorsEl.textContent = snapshot.errors_total ?? 0;
                errorRateEl.textContent = snapshot.error_rate_percent ?? 0;
                throughputEl.textContent = snapshot.throughput_rpm_avg ?? 0;
                avgLatencyEl.textContent = snapshot.avg_latency_ms ?? 0;
                generatedAt.textContent = `Actualizado: ${new Date(payload.generated_at).toLocaleString()}`;

                seriesBody.innerHTML = series.map(row => `
                    <tr>
                        <td>${row.bucket}</td>
                        <td>${row.requests}</td>
                        <td>${row.errors}</td>
                        <td>${row.avg_latency_ms}</td>
                    </tr>
                `).join('');
            } catch (error) {
                generatedAt.textContent = 'Error al actualizar métricas';
                console.error('Error loading metrics:', error);
            }
        }

        refreshBtn?.addEventListener('click', loadMetrics);
        setInterval(loadMetrics, 5000);
    });
</script>
@endpush


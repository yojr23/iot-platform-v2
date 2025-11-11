@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Detalles del Sensor: {{ $sensor->name }}</h5>
                <a href="{{ route('sensors.download', $sensor) }}" class="btn btn-primary">
                    <i class="fas fa-download"></i> Descargar Histórico JSON
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tipo:</strong> {{ $sensor->sensorType->name }}</p>
                        <p><strong>Unidad:</strong> {{ $sensor->sensorType->unit }}</p>
                        <p><strong>Rango:</strong> {{ $sensor->sensorType->min_range }} - {{ $sensor->sensorType->max_range }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Dispositivo:</strong> {{ $sensor->device->name }}</p>
                        <p><strong>Aula:</strong> {{ $sensor->device->classroom->name }}</p>
                        <p><strong>Estado:</strong> 
                            <span class="badge badge-{{ $sensor->status ? 'success' : 'danger' }}">
                                {{ $sensor->status ? 'Activo' : 'Inactivo' }}
                            </span>
                        </p>
                    </div>
                </div>
                
                <hr>
                
                <canvas id="sensorChart" style="width: 100%; height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Últimas Lecturas</h5>
            </div>
            <div class="form-group">
                <label>Filtrar por Rango de Fechas:</label>
                <div class="input-group mb-2">
                    <input type="date" id="startDate" class="form-control" placeholder="Fecha inicial">
                </div>
                <div class="input-group mb-2">
                    <input type="date" id="endDate" class="form-control" placeholder="Fecha final">
                </div>
                <button id="filterButton" class="btn btn-primary">Filtrar</button>
                <button id="resetFilter" class="btn btn-secondary ml-2">Resetear</button>
            </div>

                <table class="table table-sm" id="readingsTable">
                    <thead>
                        <tr>
                            <th>Valor</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readings as $reading)
                        <tr>
                            <td>{{ $reading->value }} {{ $sensor->sensorType->unit }}</td>
                            <td>{{ \Carbon\Carbon::parse($reading->reading_time)->format('d/m/Y ') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="d-flex justify-content-center mt-3">
                    {{ $readings->links() }}
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Obtener contexto del canvas
    const ctx = document.getElementById('sensorChart').getContext('2d');

    // Etiquetas (tiempo) y valores iniciales desde Blade
    const labels = [
        @foreach($readings->take(100) as $reading)
            "{{ \Carbon\Carbon::parse($reading->reading_time)->format('Y-m-d ') }}",
        @endforeach
    ];

    const dataValues = [
        @foreach($readings->take(100) as $reading)
            {{ floatval($reading->value) }},
        @endforeach
    ];

    // Crear gráfico de líneas con Chart.js
    const sensorChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Lectura del sensor',
                data: dataValues,
                borderColor: '#2196F3',
                backgroundColor: 'rgba(33, 150, 243, 0.2)',
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 0,
            }]
        },
        options: {
            responsive: true,
            animation: {
                duration: 500,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Tiempo'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Valor'
                    }
                }
            }
        }
    });

    // Función para agregar nuevo dato al gráfico
    function agregarNuevoDato(tiempo, valor) {
        sensorChart.data.labels.push(tiempo);
        sensorChart.data.datasets[0].data.push(valor);

        // Limitar a 100 datos
        if (sensorChart.data.labels.length > 100) {
            sensorChart.data.labels.shift();
            sensorChart.data.datasets[0].data.shift();
        }

        sensorChart.update();
    }

    // Elimina la simulación local:
    // setInterval(() => { ... }, 3000);

    // ID del sensor (puedes pasarlo desde Blade)
    const sensorId = {{ $sensor->id }};

    // Última fecha conocida (para evitar duplicados)
    let ultimaFecha = labels.length > 0 ? labels[labels.length - 1] : null;

    // Función para consultar la API periódicamente
    setInterval(() => {
        fetch(`/api/sensors/${sensorId}/readings?limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data.readings && data.readings.data && data.readings.data.length > 0) {
                    const lectura = data.readings.data[0];
                    const tiempo = lectura.reading_time.replace('T', ' ').slice(0, 19);
                    const valor = parseFloat(lectura.value);

                    // Solo agregar si es una lectura nueva
                    if (tiempo !== ultimaFecha) {
                        agregarNuevoDato(tiempo, valor);
                        ultimaFecha = tiempo;
                    }
                }
            });
    }, 3000);

    const filterButton = document.getElementById('filterButton');
    const resetFilter = document.getElementById('resetFilter');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');

    filterButton.addEventListener('click', function() {
        if (!startDate.value || !endDate.value) {
            alert('Por favor seleccione ambas fechas');
            return;
        }

        if (new Date(startDate.value) > new Date(endDate.value)) {
            alert('La fecha inicial no puede ser mayor que la fecha final');
            return;
        }

        fetch(`/sensors/${sensorId}/readings/filter?startDate=${startDate.value}&endDate=${endDate.value}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTableAndChart(data);
                } else {
                    alert('Error al filtrar los datos');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al obtener los datos filtrados');
            });
    });

    resetFilter.addEventListener('click', function() {
        startDate.value = '';
        endDate.value = '';
        location.reload(); // Recarga la página para mostrar todos los datos
    });

    function updateTableAndChart(data) {
        // Actualizar tabla
        const tbody = document.querySelector('#readingsTable tbody');
        tbody.innerHTML = '';
        
        const labels = [];
        const values = [];

        data.readings.forEach(reading => {
            const date = new Date(reading.reading_time);
            const formattedDate = date.toLocaleDateString();
            
            // Agregar fila a la tabla
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${reading.value} ${data.sensor.unit}</td>
                <td>${formattedDate}</td>
            `;
            tbody.appendChild(row);

            // Preparar datos para el gráfico
            labels.push(formattedDate);
            values.push(parseFloat(reading.value));
        });

        // Actualizar gráfico
        sensorChart.data.labels = labels;
        sensorChart.data.datasets[0].data = values;
        sensorChart.update();

        // Ocultar paginación
        const paginationDiv = document.querySelector('.d-flex.justify-content-center.mt-3');
        if (paginationDiv) {
            paginationDiv.style.display = 'none';
        }
    }

    // Agregar manejador para el botón de descarga
    document.querySelector('a[href*="sensors/download"]').addEventListener('click', function(e) {
        // Deshabilitar el botón temporalmente
        this.classList.add('disabled');
        
        // Agregar spinner
        const originalContent = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Descargando...';
        
        // Restaurar el botón después de 3 segundos
        setTimeout(() => {
            this.classList.remove('disabled');
            this.innerHTML = originalContent;
        }, 3000);
    });

</script>
@endpush
@endsection
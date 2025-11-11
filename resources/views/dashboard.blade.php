@extends('layouts.app')

@section('content')
@include('dashboard.partials.summary-cards', ['summary' => $summary])

<div class="row g-3 dashboard-row">
    @include('dashboard.partials.realtime-monitor', ['devices' => $devices])
    @include('dashboard.partials.alerts-card', ['summary' => $summary, 'activeAlertsList' => $activeAlertsList])
</div>

@include('dashboard.partials.monitors')

@push('scripts')
<script src="https://js.pusher.com/7.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], [data-tooltip="true"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        const MAX_POINTS = 10;
        const monitorsContainer = document.getElementById('monitorsContainer');
        const addMonitorButton = document.getElementById('addMonitorButton');
        const realTimeToggle = document.getElementById('realTimeToggle');
        const deviceSelectMain = document.getElementById('deviceSelect_main');
        const sensorSelectMain = document.getElementById('sensorSelect_main');
        const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;
        const preferencesEndpoints = {
            load: '{{ route('dashboard.preferences.show') }}',
            save: '{{ route('dashboard.preferences.store') }}',
        };

        // Null checks for critical elements
        if (!monitorsContainer) {
            console.error('monitorsContainer not found');
            return;
        }
        if (!addMonitorButton) {
            console.error('addMonitorButton not found');
            return;
        }
        if (!realTimeToggle) {
            console.error('realTimeToggle not found');
            return;
        }
        if (!deviceSelectMain) {
            console.error('deviceSelect_main not found');
            return;
        }
        if (!sensorSelectMain) {
            console.error('sensorSelect_main not found');
            return;
        }

        let dashboardState = {
            main: { device_id: null, sensor_id: null },
            monitors: [],
        };
        let isRestoring = false;
        let saveTimeout;
        const liveUpdateIntervals = new Map();
        const chartInstances = new Map();
        const sensorChannelSubscriptions = new Map();
        let alertsChannel = null;

        function getMonitorContainerId(chartId) {
            return `monitor-${chartId}`;
        }

        function unsubscribeFromSensorChannel(chartId) {
            const subscription = sensorChannelSubscriptions.get(chartId);
            if (!subscription || !window.pusher) {
                return;
            }

            const { channelName, handler } = subscription;
            const channel = pusher.channel(channelName);
            if (channel) {
                channel.unbind('App\\Events\\NewSensorReading', handler);

                const stillUsed = Array.from(sensorChannelSubscriptions.entries())
                    .some(([key, sub]) => key !== chartId && sub.channelName === channelName);

                if (!stillUsed) {
                    pusher.unsubscribe(channelName);
                }
            }

            sensorChannelSubscriptions.delete(chartId);
        }

        function clearLiveUpdate(chartId) {
            if (liveUpdateIntervals.has(chartId)) {
                clearInterval(liveUpdateIntervals.get(chartId));
                liveUpdateIntervals.delete(chartId);
            }
            unsubscribeFromSensorChannel(chartId);
        }

        function formatTimestamp(timestamp) {
            return timestamp ? timestamp.replace('T', ' ').slice(0, 19) : '';
        }

        function updateAlertsUI(data) {
            const alertsHeader = document.getElementById('alertsHeader');
            const alertsList = document.getElementById('alertsList');

            if (alertsHeader) {
                alertsHeader.textContent = `Últimas Alertas (${data.count})`;
            }

            if (alertsList && data.alerts) {
                if (data.alerts.length > 0) {
                    const alertsHtml = `
                        <div class="list-group list-group-flush">
                            ${data.alerts.map(alert => `
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Sensor: ${alert.sensor_reading.sensor.name}</h6>
                                        <small>${new Date(alert.created_at).toLocaleString()}</small>
                                    </div>
                                    <p class="mb-1">Mensaje: ${alert.alert_rule.message}</p>
                                    <small>Valor detectado: ${alert.sensor_reading.value} ${alert.sensor_reading.sensor.sensor_type.unit}</small>
                                </a>
                            `).join('')}
                        </div>
                    `;
                    alertsList.innerHTML = alertsHtml;
                } else {
                    alertsList.innerHTML = `
                        <div class="alert alert-info m-3 mb-0">
                            No hay alertas recientes disponibles.
                        </div>
                    `;
                }
                
                // Recalcular altura después de actualizar el contenido
                setTimeout(adjustAlertsScrollHeight, 50);
            }

            // Update the summary card count as well
            const summaryAlertsCard = document.querySelector('.card-danger .display-4');
            if (summaryAlertsCard) {
                summaryAlertsCard.textContent = data.count;
            }
        }

        function loadActiveAlerts() {
            fetch('/api/alerts/active')
                .then(response => response.json())
                .then(data => updateAlertsUI(data))
                .catch(error => console.error('Error loading active alerts:', error));
        }

        function subscribeToAlertsChannel() {
            if (!window.pusher) {
                console.error('Pusher not available');
                return;
            }

            alertsChannel = pusher.subscribe('alerts');
            alertsChannel.bind('App\\Events\\NewAlertTriggered', function(data) {
                console.log('New alert triggered:', data);
                loadActiveAlerts();
            });
        }

        function toggleAlertsChevron() {
            const alertsCollapse = document.getElementById('alertsCollapse');
            const chevron = document.getElementById('alertsChevron');
            const alertsToggle = document.querySelector('.alerts-toggle');

            if (alertsCollapse && chevron) {
                // Inicializar el estado del chevron basado en el estado inicial
                const updateChevron = (isExpanded) => {
                    if (isExpanded) {
                        chevron.classList.remove('fa-chevron-down');
                        chevron.classList.add('fa-chevron-up');
                    } else {
                        chevron.classList.remove('fa-chevron-up');
                        chevron.classList.add('fa-chevron-down');
                    }
                };

                // Estado inicial
                updateChevron(alertsCollapse.classList.contains('show'));

                // Eventos de Bootstrap collapse
                alertsCollapse.addEventListener('show.bs.collapse', function () {
                    updateChevron(true);
                });

                alertsCollapse.addEventListener('shown.bs.collapse', function () {
                    // Recalcular altura después de que se complete la animación
                    adjustAlertsScrollHeight();
                });

                alertsCollapse.addEventListener('hide.bs.collapse', function () {
                    updateChevron(false);
                });

                alertsCollapse.addEventListener('hidden.bs.collapse', function () {
                    // No hacer nada, Bootstrap maneja el display
                });

                // Asegurar que el toggle funcione correctamente
                // No interferir con el comportamiento de Bootstrap
                if (alertsToggle) {
                    // Verificar que el atributo aria-expanded se actualice
                    alertsToggle.addEventListener('click', function(e) {
                        // Bootstrap manejará el toggle automáticamente
                        // Solo necesitamos actualizar el aria-expanded después de un pequeño delay
                        setTimeout(() => {
                            const isExpanded = alertsCollapse.classList.contains('show');
                            alertsToggle.setAttribute('aria-expanded', isExpanded);
                        }, 10);
                    });
                }
            }
        }

        // Función para ajustar dinámicamente la altura del área scrolleable
        function adjustAlertsScrollHeight() {
            const alertsCard = document.getElementById('alertsCardContainer');
            const alertsCollapse = document.getElementById('alertsCollapse');
            const alertsList = document.getElementById('alertsList');
            const cardHeader = document.querySelector('.alerts-card .card-header');

            if (!alertsCard || !alertsList || !cardHeader) return;

            // Solo ajustar si el collapse está visible
            if (alertsCollapse && alertsCollapse.classList.contains('show')) {
                // Usar requestAnimationFrame para asegurar que el DOM esté actualizado
                requestAnimationFrame(() => {
                    // Obtener altura total de la tarjeta
                    const cardHeight = alertsCard.offsetHeight;
                    // Obtener altura del header
                    const headerHeight = cardHeader.offsetHeight;
                    // Calcular altura disponible para el scroll (altura total - header)
                    const availableHeight = cardHeight - headerHeight;

                    // Aplicar la altura calculada al área scrolleable
                    // Usamos maxHeight para limitar el scroll y permitir que flexbox maneje el resto
                    if (availableHeight > 0) {
                        alertsList.style.maxHeight = availableHeight + 'px';
                    }
                });
            }
        }

        // Ajustar altura cuando cambia el tamaño de la ventana
        function setupAlertsScrollResize() {
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(adjustAlertsScrollHeight, 100);
            });
        }

        function pushDataPoint(chartInstance, timestamp, value) {
            if (!chartInstance || !timestamp || Number.isNaN(value)) {
                return;
            }

            const labels = chartInstance.data.labels;
            const dataset = chartInstance.data.datasets[0].data;
            const existingIndex = labels.indexOf(timestamp);

            if (existingIndex !== -1) {
                dataset[existingIndex] = value;
            } else {
                labels.push(timestamp);
                dataset.push(value);
                if (labels.length > MAX_POINTS) {
                    labels.shift();
                    dataset.shift();
                }
            }

            chartInstance.update();
        }

        function buildReadingsUrl(sensorId, limit = MAX_POINTS, sort = null) {
            const params = new URLSearchParams({ limit });
            if (sort) {
                params.append('sort', sort);
            }
            return `/api/sensors/${sensorId}/readings?${params.toString()}`;
        }

        function subscribeToSensorChannel(chartId, sensorId, chartInstance) {
            unsubscribeFromSensorChannel(chartId);

            if (!sensorId || !window.pusher || !realTimeToggle.checked) {
                return;
            }

            const channelName = `sensor.${sensorId}`;
            const channel = pusher.channel(channelName) ?? pusher.subscribe(channelName);

            const handler = function (data) {
                if (!data || Number(data.sensor_id) !== Number(sensorId)) {
                    return;
                }
                pushDataPoint(chartInstance, formatTimestamp(data.reading_time), parseFloat(data.value));
            };

            channel.bind('App\\Events\\NewSensorReading', handler);
            sensorChannelSubscriptions.set(chartId, { channelName, handler });
        }

        function restartLiveUpdates() {
            if (!realTimeToggle.checked) {
                liveUpdateIntervals.forEach(intervalId => clearInterval(intervalId));
                liveUpdateIntervals.clear();
                sensorChannelSubscriptions.forEach((_, chartId) => unsubscribeFromSensorChannel(chartId));
                return;
            }

            const mainInstance = chartInstances.get('main');
            if (dashboardState.main.sensor_id && mainInstance) {
                startLiveUpdates('main', dashboardState.main.sensor_id, mainInstance);
                subscribeToSensorChannel('main', dashboardState.main.sensor_id, mainInstance);
            }

            dashboardState.monitors.forEach(monitor => {
                if (!monitor.sensor_id) {
                    clearLiveUpdate(monitor.id);
                    return;
                }
                const instance = chartInstances.get(monitor.id);
                if (instance) {
                    startLiveUpdates(monitor.id, monitor.sensor_id, instance);
                    subscribeToSensorChannel(monitor.id, monitor.sensor_id, instance);
                }
            });
        }

        async function persistPreferences() {
            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }
            try {
                await fetch(preferencesEndpoints.save, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ layout: dashboardState }),
                });
            } catch (error) {
                console.error('Error al guardar preferencias:', error);
            }
        }

        function persistPreferencesDebounced() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(persistPreferences, 400);
        }

        function updateStateForChart(chartId, partialState) {
            if (chartId === 'main') {
                dashboardState.main = { ...dashboardState.main, ...partialState };
                return;
            }

            const monitor = dashboardState.monitors.find(m => m.id === chartId);
            if (monitor) {
                Object.assign(monitor, partialState);
            }
        }

        async function loadSensors(deviceId, sensorSelect) {
            sensorSelect.innerHTML = '<option value="" disabled selected>Seleccione un sensor</option>';

            if (!deviceId) {
                return;
            }

            try {
                const response = await fetch(`/api/devices/${deviceId}/sensors`);
                if (!response.ok) {
                    throw new Error(`Error ${response.status}: ${response.statusText}`);
                }
                const sensors = await response.json();

                if (sensors.length === 0) {
                    sensorSelect.innerHTML += '<option value="" disabled>No hay sensores disponibles</option>';
                    return;
                }

                sensors.forEach(sensor => {
                    const option = document.createElement('option');
                    option.value = sensor.id;
                    option.textContent = sensor.name;
                    sensorSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error al cargar sensores:', error);
                sensorSelect.innerHTML = '<option value="" disabled selected>Error al cargar sensores</option>';
            }
        }

        async function loadHistoricalData(sensorId, chartInstance) {
            if (!sensorId || !chartInstance) {
                return;
            }

            try {
                const response = await fetch(buildReadingsUrl(sensorId, MAX_POINTS, 'desc'));
                if (!response.ok) {
                    throw new Error(`Error ${response.status}: ${response.statusText}`);
                }

                let rawData = await response.json();

                // Reverse to get chronological order (oldest first) since backend returns desc
                rawData = rawData.reverse();

                const labels = rawData.map(lectura => formatTimestamp(lectura.reading_time));
                const values = rawData.map(lectura => parseFloat(lectura.value));

                chartInstance.data.labels = labels;
                chartInstance.data.datasets[0].data = values;
                chartInstance.update('none');
            } catch (error) {
                console.error('Error al cargar datos históricos:', error);
            }
        }

        function startLiveUpdates(chartId, sensorId, chartInstance) {
            clearLiveUpdate(chartId);

            if (!sensorId) {
                return;
            }

            const intervalId = setInterval(async () => {
                try {
                    const response = await fetch(buildReadingsUrl(sensorId, 1));
                    if (!response.ok) {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                    const data = await response.json();
                    const lectura = data[0];
                    if (!lectura) {
                        return;
                    }

                    pushDataPoint(chartInstance, formatTimestamp(lectura.reading_time), parseFloat(lectura.value));
                } catch (error) {
                    console.error('Error al actualizar lecturas:', error);
                }
            }, 2000);

            liveUpdateIntervals.set(chartId, intervalId);
        }

        function initializeChart(chartId) {
            const deviceSelect = document.getElementById(`deviceSelect_${chartId}`);
            const sensorSelect = document.getElementById(`sensorSelect_${chartId}`);
            const canvas = document.getElementById(`sensorsChart_${chartId}`);

            if (!deviceSelect || !sensorSelect || !canvas) {
                console.error(`Elementos no encontrados para chartId: ${chartId}`);
                return null;
            }

            const ctx = canvas.getContext('2d');
            let chartInstance = Chart.getChart(canvas);
            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Lectura del sensor',
                        data: [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointRadius: 2,
                        pointBackgroundColor: '#2563eb',
                    }],
                },
                options: {
                    responsive: true,
                    animation: { duration: 500 },
                    scales: {
                        x: {
                            display: true,
                            title: { display: true, text: 'Tiempo' },
                        },
                        y: {
                            display: true,
                            title: { display: true, text: 'Valor' },
                        },
                    },
                },
            });

            chartInstances.set(chartId, chartInstance);

            deviceSelect.addEventListener('change', async function () {
                const deviceId = this.value;
                updateStateForChart(chartId, {
                    device_id: deviceId ? Number(deviceId) : null,
                    sensor_id: null,
                });

                clearLiveUpdate(chartId);
                sensorSelect.innerHTML = '<option value="" disabled selected>Seleccione un sensor</option>';
                await loadSensors(deviceId, sensorSelect);

                if (!isRestoring) {
                    persistPreferencesDebounced();
                }
            });

            sensorSelect.addEventListener('change', async function () {
                const sensorId = this.value;
                updateStateForChart(chartId, {
                    sensor_id: sensorId ? Number(sensorId) : null,
                });

                clearLiveUpdate(chartId);

                if (sensorId) {
                    await loadHistoricalData(sensorId, chartInstance);
                    if (realTimeToggle.checked) {
                        startLiveUpdates(chartId, sensorId, chartInstance);
                        subscribeToSensorChannel(chartId, sensorId, chartInstance);
                    }
                }

                if (!sensorId) {
                    unsubscribeFromSensorChannel(chartId);
                }

                if (!isRestoring) {
                    persistPreferencesDebounced();
                }
            });

            return chartInstance;
        }

        async function renderMonitorFromState(monitor) {
            const chartId = monitor.id;
            const containerId = getMonitorContainerId(chartId);

            document.getElementById(containerId)?.remove();

            const monitorHTML = `
                <div class="col-md-6 mb-3" id="${containerId}">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Monitor de Sensores</h5>
                            <button class="btn btn-danger btn-sm" onclick="removeMonitor('${chartId}')">Eliminar</button>
                        </div>
                        <div class="card-body">
                            <select id="deviceSelect_${chartId}" class="form-select mb-2 device-select" aria-label="Seleccione un dispositivo">
                                <option value="" disabled selected>Seleccione un dispositivo</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->id }}">{{ $device->name }}</option>
                                @endforeach
                            </select>
                            <select id="sensorSelect_${chartId}" class="form-select mb-2 sensor-select" aria-label="Seleccione un sensor">
                                <option value="" disabled selected>Seleccione un sensor</option>
                            </select>
                            <canvas id="sensorsChart_${chartId}" class="sensor-chart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            `;

            monitorsContainer.insertAdjacentHTML('beforeend', monitorHTML);

            const chartInstance = initializeChart(chartId);
            if (!chartInstance) {
                return;
            }

            const deviceSelect = document.getElementById(`deviceSelect_${chartId}`);
            const sensorSelect = document.getElementById(`sensorSelect_${chartId}`);

            if (monitor.device_id && deviceSelect) {
                deviceSelect.value = monitor.device_id;
                await loadSensors(monitor.device_id, sensorSelect);
            }

            if (monitor.sensor_id && sensorSelect) {
                sensorSelect.value = monitor.sensor_id;
                await loadHistoricalData(monitor.sensor_id, chartInstance);
                if (realTimeToggle.checked) {
                    startLiveUpdates(chartId, monitor.sensor_id, chartInstance);
                    subscribeToSensorChannel(chartId, monitor.sensor_id, chartInstance);
                }
            }
        }

        window.removeMonitor = function (chartId) {
            const container = document.getElementById(getMonitorContainerId(chartId));
            if (container) {
                container.remove();
            }

            const monitorIndex = dashboardState.monitors.findIndex(m => m.id === chartId);
            if (monitorIndex !== -1) {
                dashboardState.monitors.splice(monitorIndex, 1);
            }

            chartInstances.delete(chartId);
            clearLiveUpdate(chartId);

            if (!isRestoring) {
                persistPreferencesDebounced();
            }
        };

        if (addMonitorButton) {
            addMonitorButton.addEventListener('click', async function () {
                const chartId = `chart-${Date.now()}`;
                const monitorState = { id: chartId, device_id: null, sensor_id: null };
                dashboardState.monitors.push(monitorState);

                await renderMonitorFromState(monitorState);

                if (!isRestoring) {
                    persistPreferencesDebounced();
                }
            });
        }

        if (realTimeToggle) {
            realTimeToggle.addEventListener('change', function () {
                restartLiveUpdates();
            });
        }

        async function loadPreferences() {
            try {
                const response = await fetch(preferencesEndpoints.load, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Error ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                const layout = data.layout ?? {};

                dashboardState = {
                    main: {
                        device_id: layout.main?.device_id ?? null,
                        sensor_id: layout.main?.sensor_id ?? null,
                    },
                    monitors: Array.isArray(layout.monitors)
                        ? layout.monitors
                            .filter(monitor => monitor && monitor.id)
                            .map(monitor => ({
                                id: monitor.id,
                                device_id: monitor.device_id ?? null,
                                sensor_id: monitor.sensor_id ?? null,
                            }))
                        : [],
                };
            } catch (error) {
                console.error('Error al cargar preferencias:', error);
                dashboardState = {
                    main: { device_id: null, sensor_id: null },
                    monitors: [],
                };
            }
        }

        async function applyPreferences() {
            isRestoring = true;

            try {
                if (dashboardState.main.device_id) {
                    deviceSelectMain.value = dashboardState.main.device_id;
                    await loadSensors(dashboardState.main.device_id, sensorSelectMain);
                } else {
                    sensorSelectMain.innerHTML = '<option value="" disabled selected>Seleccione un sensor</option>';
                }

                if (dashboardState.main.sensor_id) {
                    sensorSelectMain.value = dashboardState.main.sensor_id;
                    await loadHistoricalData(dashboardState.main.sensor_id, chartInstances.get('main'));
                    if (realTimeToggle.checked) {
                        const mainInstance = chartInstances.get('main');
                        if (mainInstance) {
                            startLiveUpdates('main', dashboardState.main.sensor_id, mainInstance);
                            subscribeToSensorChannel('main', dashboardState.main.sensor_id, mainInstance);
                        }
                    }
                }

                monitorsContainer.innerHTML = '';
                for (const monitor of dashboardState.monitors) {
                    await renderMonitorFromState(monitor);
                }
            } finally {
                isRestoring = false;
            }
        }

        initializeChart('main');

        loadPreferences()
            .then(applyPreferences)
            .then(() => {
                subscribeToAlertsChannel();
                toggleAlertsChevron();
                adjustLayoutForSidebar();
                setupAlertsScrollResize();
                // Ajustar altura inicial después de que todo esté cargado
                setTimeout(() => {
                    adjustAlertsScrollHeight();
                }, 500);
            })
            .catch(error => console.error('No se pudieron aplicar las preferencias:', error));

        // Función para ajustar el layout cuando el sidebar se colapsa/expande
        function adjustLayoutForSidebar() {
            const sidebarElement = document.getElementById('sidebarContent');
            const dashboardRow = document.querySelector('.dashboard-row');
            const realtimeMonitor = document.querySelector('.dashboard-row > .col-md-8');
            const alertsCard = document.querySelector('.dashboard-row > .col-md-4');

            if (!sidebarElement || !dashboardRow) return;

            const checkSidebarState = () => {
                // Pequeño delay para asegurar que las clases de Bootstrap se hayan aplicado
                setTimeout(() => {
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed') || 
                                       !sidebarElement.classList.contains('show');
                    
                    if (realtimeMonitor && alertsCard) {
                        // Remover todas las clases de tamaño posibles
                        realtimeMonitor.classList.remove('col-md-7', 'col-md-8', 'col-lg-7', 'col-lg-8');
                        alertsCard.classList.remove('col-md-4', 'col-md-5', 'col-lg-4', 'col-lg-5');
                        
                        if (isCollapsed) {
                            // Cuando el sidebar está colapsado, usar más espacio disponible
                            // Ajustar proporcionalmente: 7:5 en lugar de 8:4 (mantiene similar proporción pero más espacio)
                            realtimeMonitor.classList.add('col-md-7', 'col-lg-7');
                            alertsCard.classList.add('col-md-5', 'col-lg-5');
                        } else {
                            // Cuando el sidebar está expandido, usar el tamaño original
                            realtimeMonitor.classList.add('col-md-8', 'col-lg-8');
                            alertsCard.classList.add('col-md-4', 'col-lg-4');
                        }
                    }
                    
                    // Recalcular altura del scroll después de ajustar el layout
                    setTimeout(adjustAlertsScrollHeight, 100);
                }, 50);
            };

            // Observar cambios en el estado del sidebar
            const observer = new MutationObserver(checkSidebarState);
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });
            observer.observe(sidebarElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            // Verificar estado inicial
            checkSidebarState();

            // También escuchar eventos de Bootstrap collapse
            sidebarElement.addEventListener('hidden.bs.collapse', checkSidebarState);
            sidebarElement.addEventListener('shown.bs.collapse', checkSidebarState);
            
            // Escuchar cambios en el tamaño de la ventana para reajustar
            window.addEventListener('resize', checkSidebarState);
        }
    });
</script>
@endpush
@endsection

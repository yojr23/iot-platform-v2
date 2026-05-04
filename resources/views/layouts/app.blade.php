<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SINOA') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">

    <!-- Lightweight Charts -->
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/custom-pagination.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
    <div id="app">
        @include('layouts.partials.navbar')

        <!-- Contenedor Principal -->
        <div class="container-fluid main-wrapper">
            <div class="row">
                <!-- Sidebar -->
                @auth
                    @include('layouts.partials.sidebar')
                @endauth

                <!-- Contenido Principal -->
                <main class="@auth col-md-9 col-lg-10 ms-sm-auto @else col-12 @endauth px-md-4 py-4">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @php
        $pusherKey = env('PUSHER_APP_KEY');
        $pusherCluster = env('PUSHER_APP_CLUSTER');
        $globalAlertSoundEnabled = auth()->check()
            ? (bool) \App\Models\SystemSetting::get('alert_sound_enabled', true)
            : false;
    @endphp

    @if($pusherKey && $pusherCluster)
        <!-- Pusher para actualización en tiempo real -->
        <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
        <script>
            // Enable pusher logging - don't include this in production
            Pusher.logToConsole = true;

            window.pusher = new Pusher('{{ $pusherKey }}', {
                cluster: '{{ $pusherCluster }}',
                forceTLS: true,
            });
        </script>
    @else
        <script>
            window.pusher = null;
        </script>
    @endif

    @auth
    <script>
        (function () {
            const alertSoundEnabled = @json($globalAlertSoundEnabled);
            const seenAlertIds = window.__seenAlertIdsGlobal || new Set();
            window.__seenAlertIdsGlobal = seenAlertIds;
            let alertsChannel = null;
            let pollingHandle = null;
            let snapshotInitialized = false;
            let latestSnapshot = null;
            const updateListeners = new Set();

            function getAlertColorClass(severity = 'warning') {
                const normalized = String(severity).toLowerCase();
                if (normalized === 'danger') {
                    return 'danger';
                }
                if (normalized === 'info') {
                    return 'info';
                }
                return 'warning';
            }

            function playAlertSound(severity = 'warning') {
                if (!alertSoundEnabled) {
                    return;
                }

                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    return;
                }

                if (!window.__alertsAudioContext) {
                    window.__alertsAudioContext = new AudioCtx();
                }

                const ctx = window.__alertsAudioContext;
                if (ctx.state === 'suspended') {
                    ctx.resume().then(() => playAlertSound(severity)).catch(() => {});
                    return;
                }

                const now = ctx.currentTime;
                const isDanger = String(severity).toLowerCase() === 'danger';
                const pattern = isDanger
                    ? [{ f: 880, t: 0, d: 0.12 }, { f: 740, t: 0.16, d: 0.18 }]
                    : [{ f: 740, t: 0, d: 0.12 }];

                pattern.forEach(({ f, t, d }) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(f, now + t);
                    gain.gain.setValueAtTime(0.0001, now + t);
                    gain.gain.exponentialRampToValueAtTime(0.14, now + t + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + t + d);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + t);
                    osc.stop(now + t + d + 0.02);
                });
            }

            function unlockAlertAudio() {
                if (!alertSoundEnabled) {
                    return;
                }

                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    return;
                }

                if (!window.__alertsAudioContext) {
                    window.__alertsAudioContext = new AudioCtx();
                }

                const ctx = window.__alertsAudioContext;
                const tryUnlock = () => {
                    if (ctx.state === 'suspended') {
                        ctx.resume().catch(() => {});
                    }
                    document.removeEventListener('click', tryUnlock);
                    document.removeEventListener('keydown', tryUnlock);
                    document.removeEventListener('touchstart', tryUnlock);
                };

                document.addEventListener('click', tryUnlock, { passive: true });
                document.addEventListener('keydown', tryUnlock, { passive: true });
                document.addEventListener('touchstart', tryUnlock, { passive: true });
            }

            function showAlertPopup(data) {
                let container = document.getElementById('alertPopupsContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'alertPopupsContainer';
                    container.style.position = 'fixed';
                    container.style.top = '90px';
                    container.style.right = '16px';
                    container.style.zIndex = '1085';
                    container.style.width = 'min(360px, calc(100vw - 24px))';
                    container.style.display = 'flex';
                    container.style.flexDirection = 'column';
                    container.style.gap = '8px';
                    document.body.appendChild(container);
                }

                const colorClass = getAlertColorClass(data?.severity);
                const title = data?.sensor_name ?? 'Alerta de sensor';
                const message = data?.message ?? 'Se disparó una alerta';
                const value = data?.value ?? 'N/D';
                const unit = data?.unit ?? '';
                const device = data?.device_name ?? 'Dispositivo desconocido';
                const lab = data?.lab_name ?? 'Lab no definido';
                const createdAt = data?.timestamp ? new Date(data.timestamp).toLocaleString() : new Date().toLocaleString();

                const toast = document.createElement('div');
                toast.className = `alert alert-${colorClass} shadow-sm mb-0`;
                toast.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <strong>${title}</strong>
                            <div class="small">${message}</div>
                            <div class="small">Valor: <strong>${value} ${unit}</strong></div>
                            <div class="small">Dispositivo: ${device}</div>
                            <div class="small">Laboratorio: ${lab}</div>
                            <div class="small text-muted">${createdAt}</div>
                        </div>
                        <button type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                `;

                const closeButton = toast.querySelector('.btn-close');
                closeButton?.addEventListener('click', () => toast.remove());
                container.prepend(toast);

                setTimeout(() => {
                    toast.remove();
                }, 9000);
            }

            function updateGlobalAlertBadges(count) {
                const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
                const navbarBadge = document.getElementById('unresolvedAlertsNavbarBadge');
                if (navbarBadge) {
                    navbarBadge.textContent = String(safeCount);
                    navbarBadge.classList.toggle('d-none', safeCount <= 0);
                }

                const sidebarBadge = document.getElementById('unresolvedAlertsSidebarBadge');
                if (sidebarBadge) {
                    sidebarBadge.textContent = String(safeCount);
                    sidebarBadge.classList.toggle('d-none', safeCount <= 0);
                }
            }

            function notifyUpdate(snapshot) {
                latestSnapshot = snapshot;
                updateListeners.forEach(listener => {
                    try {
                        listener(snapshot);
                    } catch (error) {
                        console.error('AppAlerts listener failed:', error);
                    }
                });
            }

            function notifyIncomingAlert(data) {
                const alertId = Number(data?.id);
                if (!Number.isFinite(alertId) || seenAlertIds.has(alertId)) {
                    return;
                }

                seenAlertIds.add(alertId);
                playAlertSound(data?.severity);
                showAlertPopup(data);
            }

            function normalizeAlertForNotification(alert) {
                if (!alert || typeof alert !== 'object') {
                    return null;
                }

                const reading = alert.sensor_reading ?? alert.sensorReading ?? {};
                const sensor = reading.sensor ?? {};
                const sensorType = sensor.sensor_type ?? sensor.sensorType ?? {};
                const rule = alert.alert_rule ?? alert.alertRule ?? {};
                const device = sensor.device ?? {};
                const lab = device.lab ?? {};

                return {
                    id: alert.id,
                    message: rule.message ?? '',
                    severity: rule.severity ?? 'warning',
                    value: reading.value ?? null,
                    unit: sensorType.unit ?? '',
                    sensor_name: sensor.name ?? '',
                    device_name: device.name ?? '',
                    lab_name: lab.name ?? '',
                    timestamp: alert.created_at ?? null,
                };
            }

            function processIncomingAlerts(alerts) {
                if (!Array.isArray(alerts)) {
                    return;
                }

                if (!snapshotInitialized) {
                    alerts.forEach(alert => {
                        const id = Number(alert?.id);
                        if (Number.isFinite(id)) {
                            seenAlertIds.add(id);
                        }
                    });
                    snapshotInitialized = true;
                    return;
                }

                [...alerts]
                    .sort((a, b) => Number(a?.id ?? 0) - Number(b?.id ?? 0))
                    .forEach(alert => {
                        const normalized = normalizeAlertForNotification(alert);
                        if (normalized) {
                            notifyIncomingAlert(normalized);
                        }
                    });
            }

            function loadActiveAlertsSnapshot() {
                fetch('/api/alerts/active', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache',
                    },
                    cache: 'no-store',
                })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => {
                        if (!data) {
                            return;
                        }
                        updateGlobalAlertBadges(data.count ?? 0);
                        processIncomingAlerts(data.alerts ?? []);
                        notifyUpdate(data);
                    })
                    .catch(() => {});
            }

            function startGlobalAlerts() {
                if (window.__appAlertsInitialized) {
                    return;
                }
                window.__appAlertsInitialized = true;

                unlockAlertAudio();
                loadActiveAlertsSnapshot();
                pollingHandle = setInterval(loadActiveAlertsSnapshot, 10000);

                if (!window.pusher) {
                    return;
                }

                alertsChannel = pusher.subscribe('alerts');
                alertsChannel.bind('App\\Events\\NewAlertTriggered', function (data) {
                    notifyIncomingAlert(data);
                    loadActiveAlertsSnapshot();
                });
            }

            window.AppAlerts = window.AppAlerts || {};
            window.AppAlerts.notifyIncomingAlert = notifyIncomingAlert;
            window.AppAlerts.updateGlobalAlertBadges = updateGlobalAlertBadges;
            window.AppAlerts.loadActiveAlertsSnapshot = loadActiveAlertsSnapshot;
            window.AppAlerts.getLatestSnapshot = function () {
                return latestSnapshot;
            };
            window.AppAlerts.subscribe = function (listener) {
                if (typeof listener !== 'function') {
                    return function () {};
                }
                updateListeners.add(listener);
                if (latestSnapshot) {
                    try {
                        listener(latestSnapshot);
                    } catch (error) {
                        console.error('AppAlerts immediate listener failed:', error);
                    }
                }
                return function () {
                    updateListeners.delete(listener);
                };
            };

            document.addEventListener('DOMContentLoaded', startGlobalAlerts);
            window.addEventListener('beforeunload', function () {
                if (pollingHandle) {
                    clearInterval(pollingHandle);
                }
                if (alertsChannel && window.pusher) {
                    pusher.unsubscribe('alerts');
                }
            });
        })();
    </script>
    @endauth

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarElement = document.getElementById('sidebarContent');
            const sidebarColumn = document.getElementById('sidebarColumn');
            const toggleButtons = document.querySelectorAll('#sidebarToggle, #sidebarToggleNavbar');

            if (sidebarElement && toggleButtons.length && sidebarColumn) {
                const collapseInstance = new bootstrap.Collapse(sidebarElement, { toggle: false });

                const updateButtonState = (button, isCollapsed) => {
                    const icon = button.querySelector('i');
                    const label = button.querySelector('span');
                    const title = isCollapsed ? 'Mostrar menú' : 'Ocultar menú';
                    button.setAttribute('aria-expanded', (!isCollapsed).toString());
                    button.setAttribute('title', title);
                    if (icon) {
                        icon.classList.toggle('fa-angles-left', !isCollapsed);
                        icon.classList.toggle('fa-angles-right', isCollapsed);
                    }
                    if (label) {
                        label.textContent = isCollapsed ? 'Mostrar panel' : 'Ocultar panel';
                    }
                };

                const updateState = (isCollapsed, triggeredButton = null) => {
                    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
                    toggleButtons.forEach(button => {
                        if (!triggeredButton || button === triggeredButton) {
                            updateButtonState(button, isCollapsed);
                        } else {
                            updateButtonState(button, !isCollapsed);
                        }
                    });
                };

                toggleButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        if (sidebarElement.classList.contains('show')) {
                            collapseInstance.hide();
                            updateState(true, button);
                        } else {
                            collapseInstance.show();
                            updateState(false, button);
                        }
                    });
                });

                sidebarElement.addEventListener('hidden.bs.collapse', () => updateState(true));
                sidebarElement.addEventListener('shown.bs.collapse', () => updateState(false));
            }
        });
    </script>

    @stack('scripts')

    <style>
        body {
            padding-top: 4.5rem;
        }

        .sidebar-column {
            transition: flex-basis .25s ease, max-width .25s ease;
        }

        .letter-spacing-wide {
            letter-spacing: .25em;
        }

        .sidebar .nav-link {
            color: #adb5bd;
            transition: color .2s ease;
        }

        .sidebar .nav-link.active {
            color: #fff;
            font-weight: 600;
        }

        body.sidebar-collapsed #sidebarColumn {
            flex: 0 0 0 !important;
            max-width: 0 !important;
            padding-left: 0;
            padding-right: 0;
        }

        body.sidebar-collapsed #sidebarContent {
            display: none;
        }

        body.sidebar-collapsed main {
            flex: 1 0 100%;
            max-width: 100%;
            transition: all 0.25s ease;
        }

        .dashboard-row {
            transition: all 0.25s ease;
        }

        .dashboard-row > [class*='col-'] {
            transition: all 0.25s ease;
        }

        @media (max-width: 767.98px) {
            body {
                padding-top: 3.75rem;
            }
        }

        .alerts-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .alerts-card .card-header {
            flex-shrink: 0;
        }

        .alerts-card .card-body {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 0;
        }

        .alerts-card #alertsCollapse {
            flex: 1 1 auto;
            min-height: 0;
        }

        /* Cuando está expandido, usar block como Bootstrap espera, pero el contenido interno usa flex */
        .alerts-card #alertsCollapse.collapse.show {
            display: block;
        }

        /* El card-body interno maneja el flexbox */
        .alerts-card #alertsCollapse .card-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .alerts-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .dashboard-row > [class*='col-'] {
            display: flex;
            transition: all 0.35s ease;
        }

        .dashboard-row > [class*='col-'] > .card {
            flex: 1 1 auto;
            transition: all 0.35s ease;
        }

        .alerts-card .collapse {
            transition: height 0.35s ease;
        }

        .alerts-card .alerts-toggle {
            transition: background-color 0.2s ease;
        }

        .alerts-card .alerts-toggle:hover {
            background-color: #f8f9fa;
        }

        #alertsChevron {
            transition: transform 0.35s ease;
        }

        .alerts-card .list-group-flush .list-group-item {
            border-left: 0;
            border-right: 0;
        }
    </style>
</body>
</html>

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

    <!-- Pusher para actualización en tiempo real -->
    <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
    <script>
        // Enable pusher logging - don't include this in production
        Pusher.logToConsole = true;

        var pusher = new Pusher('{{ env('PUSHER_APP_KEY') }}', {
            cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
            forceTLS: true
        });
    </script>

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

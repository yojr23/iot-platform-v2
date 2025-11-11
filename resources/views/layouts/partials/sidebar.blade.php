<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar sidebar-column min-vh-100" id="sidebarColumn">
    <div id="sidebarContent" class="position-sticky pt-3 collapse collapse-horizontal show">
        <div class="sidebar-heading text-center text-white mb-4">
            <h6 class="text-uppercase mb-2 letter-spacing-wide">Menú</h6>
            <button class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-2"
                    id="sidebarToggle"
                    type="button"
                    title="Ocultar menú"
                    aria-expanded="true"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right">
                <i class="fas fa-angles-left"></i>
                <span class="d-none d-lg-inline">Ocultar panel</span>
            </button>
            <small class="text-white-50 d-block mt-2">Usa este botón para contraer o mostrar el menú</small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->is('devices*') ? 'active' : '' }}" href="{{ route('devices.index') }}">
                    <i class="fas fa-microchip me-2"></i> Dispositivos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->is('sensors*') ? 'active' : '' }}" href="{{ route('sensors.index') }}">
                    <i class="fas fa-thermometer-half me-2"></i> Sensores
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->is('alerts*') ? 'active' : '' }}" href="{{ route('alerts.index') }}">
                    <i class="fas fa-bell me-2"></i> Alertas
                    @if(($unresolvedAlertsCount ?? 0) > 0)
                        <span class="badge bg-danger float-end">{{ $unresolvedAlertsCount }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item mt-3">
                <hr class="dropdown-divider bg-light">
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->is('config*') ? 'active' : '' }}" href="{{ route('config.index') }}">
                    <i class="fas fa-cog me-2"></i> Configuración
                </a>
            </li>
        </ul>
    </div>
</div>

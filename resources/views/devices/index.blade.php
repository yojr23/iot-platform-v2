@extends('layouts.app')

@section('content')
@php
    $isAdmin = auth()->user()?->is_admin ?? false;
@endphp
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <!-- Card Header with Actions -->
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-microchip fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Gestión de Dispositivos IoT</h4>
                    <p class="mb-0 opacity-75">Listado completo de dispositivos registrados</p>
                </div>
            </div>
            @if($isAdmin)
            <div>
                <a href="{{ route('devices.create') }}" class="btn btn-light btn-lg">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Dispositivo
                </a>
            </div>
            @endif
        </div>

        <!-- Alert Messages -->
        <div class="card-body pt-4">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">¡Éxito!</h5>
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">¡Error!</h5>
                    <p class="mb-0">{{ session('error') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <!-- Filters and Search -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Buscar dispositivos...">
                        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="dropdown d-inline-block me-2">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                            <li><a class="dropdown-item" href="#">Todos</a></li>
                            <li><a class="dropdown-item" href="#">Activos</a></li>
                            <li><a class="dropdown-item" href="#">Inactivos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            @foreach($deviceTypes as $type)
                            <li><a class="dropdown-item" href="#">{{ $type->name }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    <button class="btn btn-primary" id="refreshBtn">
                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Devices Table -->
            <div class="table-responsive rounded-3 border">
                <table class="table table-hover mb-0" id="devicesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th>Dispositivo</th>
                            <th>Tipo</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                            <th>Última Comunicación</th>
                            <th width="180">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $device)
                        <tr class="align-middle">
                            <td class="fw-bold text-muted">#{{ $device->id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-primary">
                                            <i class="fas fa-microchip fs-2 text-primary"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">{{ $device->name }}</h6>
                                        <small class="text-muted">{{ $device->serial_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $device->deviceType->color ?? 'info' }} py-2 px-3 fs-7">
                                    <i class="fas {{ $device->deviceType->icon ?? 'fa-microchip' }} me-2"></i>
                                    {{ $device->deviceType->name }}
                                </span>
                            </td>
                            <td>
                                <div>
                                    <span class="d-block fw-semibold">{{ $device->classroom->name }}</span>
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i> {{ $device->classroom->building }}
                                        <i class="fas fa-layer-group ms-2 me-1"></i> Piso {{ $device->classroom->floor }}
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($isAdmin)
                                        <form action="{{ route('devices.toggle-status', $device) }}" method="POST" class="d-inline toggle-status-form">
                                            @csrf
                                            <button type="submit" 
                                                class="btn btn-sm btn-{{ $device->status ? 'success' : 'secondary' }} d-flex align-items-center"
                                                title="{{ $device->status ? 'Activo - Click para desactivar' : 'Inactivo - Click para activar' }}">
                                                <i class="fas fa-power-off me-2"></i>
                                                <span>{{ $device->status ? 'Activo' : 'Inactivo' }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="badge bg-{{ $device->status ? 'success' : 'secondary' }} py-2 px-3">
                                            {{ $device->status ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($device->last_communication)
                                <div class="d-flex flex-column">
                                    <span class="text-{{ $device->status ? 'success' : 'muted' }} fw-bold">
                                        {{ $device->last_communication->diffForHumans() }}
                                    </span>
                                    <small class="text-muted">{{ $device->last_communication->format('d/m/Y H:i') }}</small>
                                </div>
                                @else
                                <span class="badge badge-light text-muted py-2 px-3">
                                    Nunca conectado
                                </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">
                                    <a href="{{ route('devices.show', $device) }}" class="btn btn-icon btn-light btn-sm me-2" title="Ver detalles">
                                        <i class="fas fa-eye text-primary"></i>
                                    </a>
                                    @if($isAdmin)
                                        <a href="{{ route('devices.edit', $device) }}" class="btn btn-icon btn-light btn-sm me-2" title="Editar">
                                            <i class="fas fa-pen text-warning"></i>
                                        </a>
                                        <form action="{{ route('devices.destroy', $device) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-light btn-sm" title="Eliminar" onclick="return confirm('¿Eliminar este dispositivo permanentemente?')">
                                                <i class="fas fa-trash text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <img src="{{ asset('assets/media/illustrations/no-devices.png') }}" alt="No devices" class="w-100px mb-4">
                                <h4 class="text-gray-700 mb-2">No hay dispositivos registrados</h4>
                                <p class="text-muted mb-4">Comienza agregando tu primer dispositivo IoT al sistema</p>
                                @if($isAdmin)
                                <a href="{{ route('devices.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Crear Dispositivo
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($devices->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    Mostrando <span class="fw-bold">{{ $devices->firstItem() }}</span> a 
                    <span class="fw-bold">{{ $devices->lastItem() }}</span> de 
                    <span class="fw-bold">{{ $devices->total() }}</span> dispositivos
                </div>
                <div>
                    {{ $devices->links() }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Toggle device status
        $('.device-status').change(function() {
            const deviceId = $(this).data('device-id');
            const isActive = $(this).is(':checked');
            
            $.ajax({
                url: '/devices/' + deviceId + '/toggle-status',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: isActive ? 1 : 0
                },
                success: function(response) {
                    Toastify({
                        text: response.message,
                        duration: 3000,
                        close: true,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#4CAF50",
                    }).showToast();
                    
                    // Reload after 1 second to see changes
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                },
                error: function(xhr) {
                    Toastify({
                        text: 'Error al actualizar el estado',
                        duration: 3000,
                        close: true,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#F44336",
                    }).showToast();
                }
            });
        });

        // Search functionality
        $('#searchInput').keyup(function() {
            const value = $(this).val().toLowerCase();
            $('#devicesTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#clearSearch').click(function() {
            $('#searchInput').val('');
            $('#devicesTable tbody tr').show();
        });

        // Refresh button
        $('#refreshBtn').click(function() {
            location.reload();
        });
    });
</script>
@endpush

@push('styles')
<style>
    .card {
        border: none;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    .card-header {
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    .table {
        --bs-table-bg: transparent;
    }
    .table > :not(:first-child) {
        border-top: none;
    }
    .symbol {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.475rem;
    }
    .symbol-50px {
        width: 50px;
        height: 50px;
    }
    .form-check-input:checked {
        background-color: #50CD89;
        border-color: #50CD89;
    }
    .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(80, 205, 137, 0.25);
    }
</style>
@endpush

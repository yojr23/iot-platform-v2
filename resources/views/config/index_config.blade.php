@extends('layouts.app')

@section('content')
@php
    $isAdmin = $isAdmin ?? (auth()->user()?->is_admin ?? false);
@endphp
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <!-- Card Header -->
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-sliders-h fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Configuración del Sistema</h4>
                    <p class="mb-0 opacity-75">Ajusta los parámetros de tu plataforma IoT</p>
                </div>
            </div>
        </div>

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
            @if(! $isAdmin)
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="fas fa-lock me-3"></i>
                <div>
                    Solo los administradores pueden modificar la configuración. Los valores se muestran en modo lectura.
                </div>
            </div>
            @endif

            <form action="{{ route('config.update') }}" method="POST">
                @csrf

                <!-- Campo oculto para mail_enabled -->
                <input type="hidden" id="mail_enabled_hidden" name="mail_enabled" value="{{ $settings['mail_enabled'] ? 1 : 0 }}">

                <!-- Configuración General -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i> Configuración General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="app_name" class="form-label">Nombre de la Aplicación</label>
                                <input type="text" class="form-control @error('app_name') is-invalid @enderror" 
                                    id="app_name" name="app_name" 
                                    value="{{ old('app_name', $settings['app_name']) }}" required @disabled(!$isAdmin)>
                                @error('app_name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="app_url" class="form-label">URL de la Aplicación</label>
                                <input type="url" class="form-control @error('app_url') is-invalid @enderror" 
                                    id="app_url" name="app_url" 
                                    value="{{ old('app_url', $settings['app_url']) }}" required @disabled(!$isAdmin)>
                                @error('app_url')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de Email -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Configuración de Email</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mail_enabled_toggle" 
                                {{ $settings['mail_enabled'] ? 'checked' : '' }}
                                onchange="updateEmailStatus(this)" @disabled(!$isAdmin)>
                            <label class="form-check-label" for="mail_enabled_toggle" id="toggleLabel">
                                <span class="badge" id="statusBadge" style="background-color: {{ $settings['mail_enabled'] ? '#198754' : '#6c757d' }}">
                                    {{ $settings['mail_enabled'] ? 'Activo' : 'Desactivado' }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($isAdmin)
                        <div class="alert alert-info border-0 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Para cambiar el servidor SMTP, credenciales y otros parámetros de email, dirígete a 
                            <a href="{{ route('email-config.index') }}" class="alert-link">Gestión de Configuración de Email</a>.
                        </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mail_from" class="form-label">Email Remitente</label>
                                <input type="email" class="form-control-plaintext" 
                                    id="mail_from" 
                                    value="{{ $settings['mail_from'] }}" readonly>
                                <small class="text-muted">Email desde el que se enviarán las notificaciones</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mail_to" class="form-label">Email de Alertas</label>
                                <input type="email" class="form-control-plaintext"
                                    id="mail_to"
                                    value="{{ $settings['mail_to'] }}" readonly>
                                <small class="text-muted">Email donde se recibirán las alertas del sistema</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de Alertas -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Configuración de Alertas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="alert_threshold" class="form-label">Umbral de Alerta (en minutos)</label>
                                <input type="number" class="form-control @error('alert_threshold') is-invalid @enderror"
                                    id="alert_threshold" name="alert_threshold"
                                    value="{{ old('alert_threshold', $settings['alert_threshold']) }}"
                                    min="0" step="1" required @disabled(!$isAdmin)>
                                @error('alert_threshold')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Tiempo máximo sin comunicación antes de generar alerta</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sensor_update_interval" class="form-label">Intervalo de Actualización (ms)</label>
                                <input type="number" class="form-control @error('sensor_update_interval') is-invalid @enderror"
                                    id="sensor_update_interval" name="sensor_update_interval"
                                    value="{{ old('sensor_update_interval', $settings['sensor_update_interval']) }}"
                                    min="1000" step="100" required @disabled(!$isAdmin)>
                                @error('sensor_update_interval')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Frecuencia con la que se actualizan las lecturas de sensores</small>
                            </div>
                        </div>
                    </div>
                </div>

                @if($isAdmin)
                <!-- Configuración Avanzada -->
                <div class="card mb-4" id="advanced-config">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i> Configuración Avanzada</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('email-config.index') }}" class="btn btn-outline-danger">
                                        <i class="fas fa-envelope me-2"></i> Gestionar Configuración de Email
                                    </a>
                                    <small class="text-muted mt-1">Configura los parámetros SMTP y credenciales de correo</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('sensor-types.create') }}" class="btn btn-outline-primary">
                                        <i class="fas fa-cogs me-2"></i> Gestionar Tipos de Sensores
                                    </a>
                                    <small class="text-muted mt-1">Crear, editar y eliminar tipos de sensores del sistema</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('device-types.create') }}" class="btn btn-outline-info">
                                        <i class="fas fa-microchip me-2"></i> Gestionar Tipos de Dispositivos
                                    </a>
                                    <small class="text-muted mt-1">Crear, editar y eliminar tipos de dispositivos IoT</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('classrooms.create') }}" class="btn btn-outline-success">
                                        <i class="fas fa-map-marker-alt me-2"></i> Gestionar Ubicaciones de Aulas
                                    </a>
                                    <small class="text-muted mt-1">Agregar y gestionar ubicaciones de aulas del campus</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('alert-rules.create') }}" class="btn btn-outline-warning">
                                        <i class="fas fa-bell me-2"></i> Configurar Reglas de Alerta
                                    </a>
                                    <small class="text-muted mt-1">Definir reglas para generar alertas automáticas</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <a href="{{ route('config.user-roles.index') }}" class="btn btn-outline-dark">
                                        <i class="fas fa-users-cog me-2"></i> Gestionar Roles de Usuarios
                                    </a>
                                    <small class="text-muted mt-1">Asigna o revoca permisos de administrador</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Información del Sistema -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Información del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Versión de PHP</label>
                                <p class="text-muted mb-0">{{ phpversion() }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Versión de Laravel</label>
                                <p class="text-muted mb-0">{{ app()->version() }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Entorno</label>
                                <p class="text-muted mb-0">
                                    <span class="badge bg-{{ app()->environment('production') ? 'danger' : 'info' }}">
                                        {{ app()->environment() }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Base de Datos</label>
                                <p class="text-muted mb-0">{{ config('database.default') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="d-flex justify-content-between">
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Volver
                    </a>
                    @if($isAdmin)
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Guardar Cambios
                        </button>
                    @else
                        <button type="button" class="btn btn-primary" disabled>
                            <i class="fas fa-lock me-2"></i> Solo lectura
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .card-header.bg-light {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6;
    }

    .form-label {
        font-weight: 500;
        color: #495057;
    }

    .form-control {
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
        padding: 0.625rem 0.875rem;
    }

    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .form-control-plaintext {
        display: block;
        padding: 0.625rem 0;
        margin-bottom: 0.5rem;
        color: #6c757d;
        font-weight: 500;
        border: none;
        background-color: transparent;
        cursor: default;
    }

    .form-control-plaintext:focus {
        outline: none;
        box-shadow: none;
    }

    small.text-muted {
        font-size: 0.875rem;
        display: block;
        margin-top: 0.25rem;
    }
</style>

<script>
    function updateEmailStatus(checkbox) {
        const hiddenInput = document.getElementById('mail_enabled_hidden');
        const statusBadge = document.getElementById('statusBadge');
        
        if (checkbox.checked) {
            hiddenInput.value = 1;
            statusBadge.textContent = 'Activo';
            statusBadge.style.backgroundColor = '#198754';
        } else {
            hiddenInput.value = 0;
            statusBadge.textContent = 'Desactivado';
            statusBadge.style.backgroundColor = '#6c757d';
        }
    }
</script>
@endsection

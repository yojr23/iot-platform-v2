    @extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <!-- Card Header -->
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-cogs fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Gestionar Tipos de Dispositivos</h4>
                    <p class="mb-0 opacity-75">Crear, editar y eliminar tipos de dispositivos del sistema</p>
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

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Error</h5>
                    <p class="mb-0">{{ session('error') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <!-- Formulario para crear nuevo tipo -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Crear Nuevo Tipo de Dispositivo</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('device-types.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nombre del Tipo</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" required
                                       value="{{ old('name') }}"
                                       placeholder="Ej: Calidad de Ambiente, Pánico, Desastres">
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="2"
                                          placeholder="Describe brevemente el propósito de este tipo de dispositivo">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Crear Tipo
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de tipos existentes -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> Tipos de Dispositivos Existentes</h5>
                </div>
                <div class="card-body">
                    @if($deviceTypes->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-tag me-2"></i>Nombre</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Descripción</th>
                                    <th><i class="fas fa-cogs me-2"></i>Dispositivos</th>
                                    <th><i class="fas fa-calendar me-2"></i>Creado</th>
                                    <th><i class="fas fa-tools me-2"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($deviceTypes as $deviceType)
                                <tr>
                                    <td>
                                        <strong>{{ $deviceType->name }}</strong>
                                    </td>
                                    <td>
                                        {{ $deviceType->description ?: 'Sin descripción' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $deviceType->devices()->count() }} dispositivos</span>
                                    </td>
                                    <td>
                                        {{ $deviceType->created_at->format('d/m/Y') }}
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('device-types.edit', $deviceType->id) }}"
                                               class="btn btn-outline-warning btn-sm"
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('device-types.destroy', $deviceType->id) }}"
                                                  method="POST"
                                                  style="display: inline;"
                                                  onsubmit="return confirm('¿Estás seguro de eliminar este tipo de dispositivo?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-outline-danger btn-sm"
                                                        title="Eliminar"
                                                        {{ $deviceType->devices()->count() > 0 ? 'disabled' : '' }}>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No hay tipos de dispositivos registrados</h5>
                        <p class="text-muted">Crea tu primer tipo de dispositivo usando el formulario arriba.</p>
                    </div>
                    @endif
                </div>
            </div>
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

    .table th {
        border-top: none;
        font-weight: 600;
    }

    .badge {
        font-size: 0.75rem;
    }

    .btn-group .btn {
        margin-right: 0.25rem;
    }

    .btn-group .btn:last-child {
        margin-right: 0;
    }
</style>
@endsection

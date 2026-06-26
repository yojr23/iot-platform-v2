@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <!-- Card Header -->
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-edit fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Editar Tipo de Dispositivo</h4>
                    <p class="mb-0 opacity-75">Modificar la información del tipo de dispositivo</p>
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

            <!-- Formulario para editar tipo -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i> Información del Tipo de Dispositivo</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('device-types.update', $deviceType->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nombre del Tipo</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" required
                                       value="{{ old('name', $deviceType->name) }}"
                                       placeholder="Ej: Calidad de Ambiente, Pánico, Desastres">
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="2"
                                          placeholder="Describe brevemente el propósito de este tipo de dispositivo">{{ old('description', $deviceType->description) }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Información adicional -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="fas fa-cogs me-2"></i>Dispositivos Asociados</h6>
                                        <p class="card-text mb-0">
                                            <span class="badge bg-info fs-6">{{ $deviceType->devices()->count() }} dispositivos</span>
                                        </p>
                                        <small class="text-muted">Este tipo está siendo usado por dispositivos existentes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="fas fa-calendar me-2"></i>Información de Creación</h6>
                                        <p class="card-text mb-0">
                                            Creado: {{ $deviceType->created_at->format('d/m/Y H:i') }}<br>
                                            Última modificación: {{ $deviceType->updated_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('device-types.create') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Actualizar Tipo
                            </button>
                        </div>
                    </form>
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

    .card.bg-light {
        border: 1px solid #dee2e6;
    }

    .badge {
        font-size: 0.75rem;
    }
</style>
@endsection

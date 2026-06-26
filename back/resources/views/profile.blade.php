@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Perfil de Usuario</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                        </div>
                        <div class="col-md-8">
                            <h5>{{ Auth::user()->name }}</h5>
                            <p class="text-muted">{{ Auth::user()->email }}</p>
                            <hr>
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>ID de Usuario:</strong><br>
                                    {{ Auth::user()->id }}
                                </div>
                                <div class="col-sm-6">
                                    <strong>Fecha de Registro:</strong><br>
                                    {{ Auth::user()->created_at->format('d/m/Y') }}
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-sm-6">
                                    <strong>Última Actualización:</strong><br>
                                    {{ Auth::user()->updated_at->format('d/m/Y H:i') }}
                                </div>
                                <div class="col-sm-6">
                                    <strong>Rol:</strong><br>
                                    @if(Auth::user()->is_admin)
                                        Administrador
                                    @else
                                        Usuario
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

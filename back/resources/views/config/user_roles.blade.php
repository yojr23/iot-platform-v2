@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-users-cog fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Gestión de Roles de Usuario</h4>
                    <p class="mb-0 opacity-75">Otorga acceso administrativo a los usuarios que lo requieran</p>
                </div>
            </div>
            <a href="{{ route('config.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Volver a configuración
            </a>
        </div>

        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <strong>¡Actualizado!</strong> {{ session('success') }}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th class="text-center">Rol actual</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $user->role?->code === 'superadmin' ? 'bg-danger' : ($user->role?->code === 'admin' ? 'bg-warning' : 'bg-secondary') }}">
                                        {{ $user->role?->name ?? 'Sin rol' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($user->role?->code !== 'superadmin')
                                    <form action="{{ route('config.user-roles.update', $user) }}" method="POST" class="d-inline-flex align-items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_admin" value="{{ $user->role?->code === 'admin' ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm {{ $user->role?->code === 'admin' ? 'btn-outline-secondary' : 'btn-outline-danger' }}">
                                            <i class="fas {{ $user->role?->code === 'admin' ? 'fa-user-minus' : 'fa-user-shield' }} me-1"></i>
                                            {{ $user->role?->code === 'admin' ? 'Revocar admin' : 'Hacer admin' }}
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="card shadow border-0">
        <!-- Card Header -->
        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-envelope fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0">Gestión de Configuración de Email</h4>
                    <p class="mb-0 opacity-75">Configura los parámetros de envío de correos electrónicos</p>
                </div>
            </div>
            <a href="{{ route('config.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Volver a Configuración
            </a>
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
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Error</h5>
                    <p class="mb-0">{{ session('error') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <form action="{{ route('email-config.update') }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Información Importante -->
                <div class="alert alert-info border-0 rounded-3 mb-4">
                    <div class="d-flex">
                        <i class="fas fa-lightbulb fa-2x me-3 text-info"></i>
                        <div>
                            <h5 class="mb-2">¿Cómo generar una contraseña de aplicación en Gmail?</h5>
                            <p class="mb-2">Para usar Gmail con esta aplicación, necesitas generar una contraseña de aplicación (App Password) en tu cuenta de Google.</p>
                            <a href="https://www.youtube.com/watch?v=h4eVrDSf8Eg" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-video me-2"></i> Ver tutorial en YouTube
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Configuración SMTP -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i> Configuración SMTP</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mail_mailer" class="form-label">Mailer</label>
                                <select class="form-select @error('mail_mailer') is-invalid @enderror" 
                                    id="mail_mailer" name="mail_mailer" required>
                                    <option value="smtp" {{ $mailSettings['mail_mailer'] === 'smtp' ? 'selected' : '' }}>SMTP</option>
                                    <option value="mailgun" {{ $mailSettings['mail_mailer'] === 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                                    <option value="postmark" {{ $mailSettings['mail_mailer'] === 'postmark' ? 'selected' : '' }}>Postmark</option>
                                    <option value="ses" {{ $mailSettings['mail_mailer'] === 'ses' ? 'selected' : '' }}>AWS SES</option>
                                    <option value="sendmail" {{ $mailSettings['mail_mailer'] === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                    <option value="log" {{ $mailSettings['mail_mailer'] === 'log' ? 'selected' : '' }}>Log</option>
                                </select>
                                @error('mail_mailer')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mail_host" class="form-label">Host SMTP</label>
                                <input type="text" class="form-control @error('mail_host') is-invalid @enderror" 
                                    id="mail_host" name="mail_host" 
                                    value="{{ old('mail_host', $mailSettings['mail_host']) }}" 
                                    placeholder="ej: smtp.gmail.com" required>
                                @error('mail_host')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mail_port" class="form-label">Puerto</label>
                                <input type="number" class="form-control @error('mail_port') is-invalid @enderror" 
                                    id="mail_port" name="mail_port" 
                                    value="{{ old('mail_port', $mailSettings['mail_port']) }}" 
                                    placeholder="ej: 587" required>
                                @error('mail_port')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Para Gmail: 587 (TLS) o 465 (SSL)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mail_encryption" class="form-label">Encriptación</label>
                                <select class="form-select @error('mail_encryption') is-invalid @enderror" 
                                    id="mail_encryption" name="mail_encryption" required>
                                    <option value="tls" {{ $mailSettings['mail_encryption'] === 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="ssl" {{ $mailSettings['mail_encryption'] === 'ssl' ? 'selected' : '' }}>SSL</option>
                                </select>
                                @error('mail_encryption')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Credenciales de Email -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i> Credenciales de Email</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning border-0 mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> La contraseña se almacenará de forma segura. Para Gmail, usa la contraseña de aplicación generada desde tu cuenta.
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mail_username" class="form-label">Email/Usuario SMTP</label>
                                <input type="email" class="form-control @error('mail_username') is-invalid @enderror" 
                                    id="mail_username" name="mail_username" 
                                    value="{{ old('mail_username', $mailSettings['mail_username']) }}" 
                                    placeholder="ej: tu@gmail.com" required>
                                @error('mail_username')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Usuario de tu cuenta de correo</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mail_password" class="form-label">Contraseña de Aplicación</label>
                                <input type="password" class="form-control @error('mail_password') is-invalid @enderror" 
                                    id="mail_password" name="mail_password" 
                                    value="{{ old('mail_password', $mailSettings['mail_password']) }}" 
                                    placeholder="Contraseña de aplicación de Gmail" required>
                                @error('mail_password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Para Gmail: Contraseña de aplicación (16 caracteres)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de Remitente -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i> Información del Remitente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mail_from_address" class="form-label">Email del Remitente</label>
                                <input type="email" class="form-control @error('mail_from_address') is-invalid @enderror" 
                                    id="mail_from_address" name="mail_from_address" 
                                    value="{{ old('mail_from_address', $mailSettings['mail_from_address']) }}" 
                                    placeholder="ej: noreply@tuapp.com" required>
                                @error('mail_from_address')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Email desde el cual se enviarán los correos</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mail_from_name" class="form-label">Nombre del Remitente</label>
                                <input type="text" class="form-control @error('mail_from_name') is-invalid @enderror" 
                                    id="mail_from_name" name="mail_from_name" 
                                    value="{{ old('mail_from_name', $mailSettings['mail_from_name']) }}" 
                                    placeholder="ej: SINOA" required>
                                @error('mail_from_name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Nombre que aparecerá como remitente en los correos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email de Alertas -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Email para Alertas del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="mail_to" class="form-label">Email de Destinatario para Alertas</label>
                            <input type="email" class="form-control @error('mail_to') is-invalid @enderror" 
                                id="mail_to" name="mail_to" 
                                value="{{ old('mail_to', $mailSettings['mail_to']) }}" 
                                placeholder="ej: alertas@tuempresa.com" required>
                            @error('mail_to')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Email donde se recibirán las alertas y notificaciones del sistema</small>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="d-flex justify-content-between gap-2">
                    <a href="{{ route('config.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Volver
                    </a>
                    <div class="gap-2 d-flex">
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                            <i class="fas fa-paper-plane me-2"></i> Enviar Email de Prueba
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Guardar Configuración
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Email de Prueba -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="testEmailModalLabel">
                    <i class="fas fa-envelope me-2"></i> Enviar Email de Prueba
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('email-config.test') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Email de Prueba</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" 
                            value="{{ auth()->user()->email }}" placeholder="Ingresa un email" required>
                        <small class="text-muted">Se enviará un email de prueba a esta dirección para verificar que la configuración es correcta</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-paper-plane me-2"></i> Enviar Prueba
                    </button>
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

    .form-control, .form-select {
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
        padding: 0.625rem 0.875rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    small.text-muted {
        font-size: 0.875rem;
        display: block;
        margin-top: 0.25rem;
    }

    .alert {
        border-radius: 0.5rem;
    }
</style>
@endsection

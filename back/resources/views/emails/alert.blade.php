<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alerta de Peligro</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#b91c1c;color:#ffffff;padding:16px 20px;">
                            <h1 style="margin:0;font-size:20px;line-height:1.3;">Alerta de Peligro Detectada</h1>
                            <p style="margin:8px 0 0;font-size:13px;opacity:0.95;">ID de alerta: {{ $alert_id ?? 'N/D' }} | Severidad: {{ $severity ?? 'DANGER' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px;">
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.5;">
                                Se detectó un evento fuera de los límites esperados y requiere revisión.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:14px;">
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;width:40%;"><strong>Dispositivo</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $device }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Ubicación</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $location }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Sensor</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $sensor }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Tipo de sensor</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $sensor_type ?? 'N/D' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Regla</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $rule_name ?? 'N/D' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Umbral mínimo</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $threshold_min ?? 'No aplica' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Umbral máximo</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $threshold_max ?? 'No aplica' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Valor detectado</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">
                                        {{ $value }}@if(!empty($unit)) {{ $unit }}@endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Fecha/Hora detección</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $detected_at ?? 'N/D' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><strong>Mensaje</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $alert_message }}</td>
                                </tr>
                            </table>

                            <p style="margin:14px 0 0;font-size:12px;color:#4b5563;">
                                Notificación automática de SINOA.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

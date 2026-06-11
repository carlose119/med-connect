<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta de Médico Creada</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 15px; color: #1f2937; background: #f9fafb; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden; }
        .header { background: #1e40af; color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; opacity: 0.85; font-size: 13px; }
        .content { padding: 28px 32px; }
        .content h2 { color: #1e40af; margin: 0 0 16px; font-size: 18px; }
        .info-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 16px; margin: 16px 0; }
        .info-box p { margin: 6px 0; }
        .info-box strong { color: #1e40af; }
        .btn { display: inline-block; background: #1e40af; color: white; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; margin: 20px 0; font-size: 16px; }
        .warning { background: #fffbeb; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px; margin: 16px 0; font-size: 13px; color: #b45309; }
        .expiry-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px; margin: 16px 0; font-size: 13px; color: #1e40af; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MedConnect</h1>
            <p>Tu cuenta de médico ha sido creada</p>
        </div>
        <div class="content">
            <h2>¡Bienvenido/a, {{ $doctorName }}!</h2>
            <p>El administrador del sistema ha creado tu cuenta de médico en <strong>MedConnect</strong>.</p>

            <div class="info-box">
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Rol:</strong> Médico</p>
            </div>

            <p>Hacé clic en el siguiente botón para activar tu cuenta y establecer tu contraseña:</p>

            <a href="{{ $activationUrl }}" class="btn">Activar mi cuenta</a>

            <div class="expiry-note">
                📅 Este enlace expira el {{ $expiresAt->format('d/m/Y') }}.
            </div>

            <div class="warning">
                ⚠️ Si no activás tu cuenta dentro de los 7 días, el enlace dejará de funcionar. Contactá al administrador para solicitar uno nuevo.
            </div>

            <p>Si tenés alguna duda o no solicitaste esta cuenta, contactá al administrador del sistema.</p>
        </div>
        <div class="footer">
            MedConnect — Sistema de Gestión Médica<br>
            Este es un email automático, no respondas a este mensaje.
        </div>
    </div>
</body>
</html>
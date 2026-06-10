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
        .credential-box { background: #1e293b; color: #f8fafc; border-radius: 6px; padding: 16px; margin: 20px 0; font-family: 'Courier New', monospace; font-size: 14px; }
        .credential-box .label { color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 6px; }
        .credential-box .value { margin: 4px 0; }
        .btn { display: inline-block; background: #1e40af; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin: 16px 0; }
        .warning { background: #fffbeb; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px; margin: 16px 0; font-size: 13px; color: #b45309; }
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

            <p>Ingresá a tu cuenta con las siguientes credenciales:</p>

            <div class="credential-box">
                <div class="label">Contraseña temporal</div>
                <div class="value">{{ $tempPassword }}</div>
            </div>

            <a href="{{ $loginUrl }}" class="btn">Ingresar a MedConnect</a>

            <div class="warning">
                ⚠️ Por seguridad, te recomendamos cambiar tu contraseña inmediatamente después de iniciar sesión.
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
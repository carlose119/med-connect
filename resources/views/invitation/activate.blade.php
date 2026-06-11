<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activar tu cuenta — MedConnect</title>
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
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .form-group input[type="password"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        .form-group input[type="password"]:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .error-text { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .btn { display: inline-block; background: #1e40af; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer; width: 100%; text-align: center; font-size: 15px; }
        .btn:hover { background: #1e3a8a; }
        .success-banner { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; color: #166534; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MedConnect</h1>
            <p>Activación de cuenta de médico</p>
        </div>
        <div class="content">
            <h2>Activar tu cuenta</h2>
            <p>Establecé tu contraseña para ingresar a MedConnect.</p>

            @if(session('success'))
                <div class="success-banner">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('invitation.activate', ['token' => $token]) }}">
                @csrf

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Mínimo 8 caracteres, letras y números"
                        required
                        autocomplete="new-password"
                    >
                    @error('password')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Repetí tu contraseña"
                        required
                        autocomplete="new-password"
                    >
                    @error('password_confirmation')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn">Activar mi cuenta</button>
            </form>

            <div class="info-box">
                <p>Una vez activada, tu cuenta queda lista para usar.</p>
            </div>
        </div>
        <div class="footer">
            MedConnect — Sistema de Gestión Médica<br>
            Este es un email automático, no respondas a este mensaje.
        </div>
    </div>
</body>
</html>
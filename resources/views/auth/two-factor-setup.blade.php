<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Activar 2FA</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); width: 100%; max-width: 480px; text-align: center; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        p { color: #6b7280; text-align: left; }
        img { width: 200px; height: 200px; margin: 1rem auto; display: block; }
        code { display: block; background: #f3f4f6; padding: 0.5rem; border-radius: 4px; font-family: monospace; word-break: break-all; margin: 0.5rem 0 1rem; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; font-size: 1.25rem; letter-spacing: 0.25em; text-align: center; }
        button { width: 100%; background: #4f46e5; color: white; padding: 0.6rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Activar 2FA</h1>
        <p>Escanea este QR con tu app autenticadora (Google Authenticator, Authy, etc.) y luego introduce el codigo que genere.</p>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}" alt="QR 2FA">
        <p style="text-align:center;font-size:0.875rem;">O introduce esta clave manualmente:</p>
        <code>{{ $secret }}</code>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('2fa.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" autofocus required>
            <button type="submit">Activar 2FA</button>
        </form>
    </div>
</body>
</html>

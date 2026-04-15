<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Verificacion 2FA</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        p { color: #6b7280; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; font-size: 1.25rem; letter-spacing: 0.25em; text-align: center; }
        button { width: 100%; background: #4f46e5; color: white; padding: 0.6rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Codigo de verificacion</h1>
        <p>Introduce el codigo de 6 digitos de tu app autenticadora.</p>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required>
            <button type="submit">Verificar</button>
        </form>
    </div>
</body>
</html>

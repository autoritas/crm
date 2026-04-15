<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Iniciar sesion</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        label { display: block; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500; }
        input[type=email], input[type=password] { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; margin-bottom: 1rem; }
        button { width: 100%; background: #4f46e5; color: white; padding: 0.6rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        button:hover { background: #4338ca; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.75rem; }
        .remember { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Iniciar sesion</h1>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Contrasena</label>
            <input type="password" id="password" name="password" required>

            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Recordarme</label>
            </div>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>

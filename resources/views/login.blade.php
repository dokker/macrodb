<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MacroDB – Bejelentkezés</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #f4f4f5; }
        form { background: #fff; padding: 2rem; border-radius: .5rem; width: min(22rem, 90vw); display: grid; gap: 1rem; box-shadow: 0 1px 4px #0002; }
        input, button { padding: .6rem; font: inherit; }
        .error { color: #b91c1c; font-size: .9rem; }
    </style>
</head>
<body>
    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <h1>MacroDB</h1>
        <input type="email" name="email" value="{{ old('email') }}" placeholder="E-mail" required autofocus>
        <input type="password" name="password" placeholder="Jelszó" required>
        @error('email')<div class="error">{{ $message }}</div>@enderror
        <button type="submit">Belépés</button>
    </form>
</body>
</html>

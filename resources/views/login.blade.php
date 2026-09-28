<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2f7d5b">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>MacroDB – Bejelentkezés</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-dvh place-items-center px-4">
    <form method="POST" action="{{ url('/login') }}" class="card w-full max-w-sm space-y-4 p-6">
        @csrf
        <div class="flex items-center gap-3">
            <img src="/icons/icon.svg" alt="" class="size-10 rounded-xl">
            <h1 class="text-2xl font-bold">MacroDB</h1>
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" class="field mt-1" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div>
            <label class="label" for="password">Jelszó</label>
            <input id="password" class="field mt-1" type="password" name="password" autocomplete="current-password" required>
        </div>
        @error('email')<p class="text-sm text-protein" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary w-full">Belépés</button>
    </form>
</body>
</html>

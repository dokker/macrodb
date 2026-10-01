<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fcfbf9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1014" media="(prefers-color-scheme: dark)">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>MacroDB – Bejelentkezés</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh justify-center px-5 pt-[calc(3rem+env(safe-area-inset-top))] pb-8 sm:items-center sm:pt-8">
    <main class="w-full max-w-sm">
        <img src="/icons/icon.svg" alt="" class="size-11 rounded-xl">
        <h1 class="mt-5 text-[28px] font-bold leading-tight tracking-tight">Belépés a MacroDB-be</h1>
        <p class="mt-1 text-[15px] text-muted">Az étkezésnaplód és a napi makróid egy helyen.</p>

        <form method="POST" action="{{ url('/login') }}" class="mt-7 space-y-4">
            @csrf
            <div>
                <label class="label" for="email">E-mail</label>
                <input id="email" class="field mt-1.5" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
            <div>
                <label class="label" for="password">Jelszó</label>
                <input id="password" class="field mt-1.5" type="password" name="password" autocomplete="current-password" required>
            </div>
            @error('email')<p class="text-sm text-protein" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full">Belépés</button>
        </form>
    </main>
</body>
</html>

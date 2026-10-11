<!doctype html>
<html lang="es" data-theme="light">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? 'App Cobranzas' }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-base-200 text-base-content"><main class="flex min-h-screen items-center justify-center p-4"><div class="card w-full max-w-md bg-base-100 shadow-xl"><div class="card-body gap-5">
    <div><span class="badge badge-primary badge-outline mb-3">App Cobranzas</span><h1 class="text-2xl font-bold">{{ $heading ?? 'Bienvenido' }}</h1>@isset($description)<p class="mt-2 text-sm text-base-content/65">{{ $description }}</p>@endisset</div>
    @if (session('status')) <div class="alert alert-success text-sm" role="status">{{ session('status') }}</div> @endif
    {{ $slot }}
</div></div></main></body></html>

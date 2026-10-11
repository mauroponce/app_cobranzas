<x-layouts.guest title="Aceptar invitación">
    <h1 class="text-2xl font-bold">Invitación a {{ $invitation->tenant->name }}</h1>
    <p class="mt-3">Correo invitado: <strong>{{ $invitation->email }}</strong></p><p class="mt-2">Rol: {{ \App\Domain\Tenancy\RoleMatrix::label($invitation->role) }}. Vence {{ $invitation->expires_at->format('d/m/Y H:i') }}.</p>
    @if($errors->any())<div class="alert alert-error mt-4">{{ $errors->first() }}</div>@endif
    @auth
        @if(strcasecmp(auth()->user()->email, $invitation->email) !== 0)<p class="alert alert-warning mt-5">Esta invitación corresponde a otro correo. Cerrá sesión e ingresá con {{ $invitation->email }}.</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn btn-outline">Cerrar sesión</button></form>
        @else<form method="POST" action="{{ route('invitations.accept', $token) }}" class="mt-5">@csrf<button class="btn admin-action">Aceptar invitación</button></form>@endif
    @else
        @if(\App\Models\User::whereRaw('lower(email) = ?', [strtolower($invitation->email)])->exists())<p class="mt-5">Ya tenés una cuenta. Ingresá con {{ $invitation->email }} y volvé a abrir este enlace.</p><a class="btn admin-action mt-4" href="{{ route('login') }}">Iniciar sesión</a>
        @else<form method="POST" action="{{ route('invitations.accept', $token) }}" class="mt-5 space-y-4">@csrf<x-ui.form-field name="name" label="Nombre" required /><x-ui.form-field name="password" label="Contraseña (mínimo 12 caracteres)" type="password" required /><x-ui.form-field name="password_confirmation" label="Confirmar contraseña" type="password" required /><button class="btn admin-action w-full">Crear cuenta y aceptar</button></form>@endif
    @endauth
</x-layouts.guest>

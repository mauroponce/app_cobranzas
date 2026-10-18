<x-layouts.app title="Soporte del piloto">
    <div class="space-y-6">
        @if (session('status'))
            <div class="alert alert-success" role="status"><span>{{ session('status') }}</span></div>
        @endif

        <header>
            <p class="collection-eyebrow">Diagnóstico operativo</p>
            <h1 class="admin-title">Soporte del piloto</h1>
            <p class="admin-muted mt-1">Consultá dispositivos, sincronizaciones e incidencias de la empresa activa sin acceder directamente a la base.</p>
        </header>

        <div class="grid gap-3 md:grid-cols-4">
            @foreach(['applied' => 'Aplicadas', 'pending_review' => 'En revisión', 'rejected' => 'Rechazadas', 'failed' => 'Fallidas'] as $status => $label)
                <div class="collection-stat"><span>{{ $label }}</span><strong>{{ $operationCounts[$status] ?? 0 }}</strong></div>
            @endforeach
        </div>

        <section class="collection-panel">
            <div class="collection-toolbar"><div><h2>Dispositivos</h2><p>La revocación invalida únicamente el token ligado al dispositivo seleccionado.</p></div></div>
            <div class="overflow-x-auto">
                <table class="table collection-table">
                    <thead><tr><th>Usuario</th><th>Dispositivo</th><th>Última conexión</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($devices as $device)
                            <tr>
                                <td><strong>{{ $device->membership?->user?->name }}</strong><small>{{ $device->membership?->role }}</small></td>
                                <td><code>…{{ substr($device->device_identifier, -12) }}</code><small>{{ $device->id }}</small></td>
                                <td>{{ $device->last_seen_at?->format('d/m/Y H:i:s') ?? 'Sin registro' }}</td>
                                <td><span class="collection-badge {{ $device->revoked_at ? 'is-error' : 'is-approved' }}">{{ $device->revoked_at ? 'Revocado' : 'Activo' }}</span></td>
                                <td>
                                    @if(! $device->revoked_at)
                                        @can('authorization.manage')
                                            <form method="POST" action="{{ route('support.devices.revoke', $device->id) }}">@csrf<button class="btn btn-sm btn-error" onclick="return confirm('¿Revocar este dispositivo e invalidar su sesión móvil?')">Revocar</button></form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8">No hay dispositivos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="collection-panel">
            <div class="collection-toolbar"><div><h2>Operaciones móviles recientes</h2><p>Usá el UUID para correlacionar una declaración con usuario y dispositivo. El payload no se muestra.</p></div></div>
            <div class="overflow-x-auto">
                <table class="table collection-table">
                    <thead><tr><th>UUID</th><th>Usuario</th><th>Tipo</th><th>Estado</th><th>Registrada</th><th>Procesada</th></tr></thead>
                    <tbody>
                        @forelse($operations as $operation)
                            <tr><td><code>{{ $operation->operation_id }}</code><small>Dispositivo {{ $operation->device_id }}</small></td><td>{{ $operation->user_name }}<small>{{ $operation->user_id }}</small></td><td>{{ $operation->type }}</td><td><span class="collection-badge">{{ $operation->status }}</span></td><td>{{ \Carbon\Carbon::parse($operation->recorded_at)->format('d/m/Y H:i:s') }}</td><td>{{ $operation->processed_at ? \Carbon\Carbon::parse($operation->processed_at)->format('d/m/Y H:i:s') : 'Pendiente' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8">No hay operaciones sincronizadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="collection-panel p-5"><h2 class="text-lg font-bold">Incidencias recientes</h2>@forelse($incidents as $incident)<p class="mt-3"><strong>{{ $incident->type }}</strong> · {{ $incident->status }}<small class="block">{{ $incident->id }} · {{ $incident->created_at?->format('d/m/Y H:i:s') }}</small></p>@empty<p class="admin-muted mt-3">No hay incidencias.</p>@endforelse</section>
            <section class="collection-panel p-5"><h2 class="text-lg font-bold">Auditoría reciente</h2>@forelse($activity as $event)<p class="mt-3"><strong>{{ $event->action }}</strong><small class="block">{{ $event->actor?->name ?? 'Sistema' }} · {{ $event->created_at?->format('d/m/Y H:i:s') }} · {{ $event->subject_id }}</small></p>@empty<p class="admin-muted mt-3">No hay eventos.</p>@endforelse</section>
        </div>
    </div>
</x-layouts.app>

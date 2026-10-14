<x-layouts.app title="Cobradores">
    <div class="mb-6">
        <p class="admin-muted font-bold tracking-wide">OPERACIONES</p>
        <h1 class="admin-title mt-1">Cobradores</h1>
        <p class="admin-muted mt-1">Equipo activo, cartera asignada y rutas publicadas para hoy.</p>
    </div>

    <x-ui.card class="overflow-x-auto p-5">
        <table class="table">
            <thead>
                <tr><th>Cobrador</th><th>Correo</th><th>Clientes asignados</th><th>Rutas de hoy</th><th>Alta</th></tr>
            </thead>
            <tbody>
                @forelse ($collectors as $collector)
                    <tr>
                        <td>{{ $collector->user->name }}</td>
                        <td>{{ $collector->user->email }}</td>
                        <td>{{ $collector->customers_count }}</td>
                        <td>{{ $collector->routes_count }}</td>
                        <td>{{ $collector->joined_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay cobradores activos en esta empresa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.app>

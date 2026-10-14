<div class="collection-dashboard space-y-5">
    @if (session('status')) <div class="alert alert-success"><span>{{ session('status') }}</span></div> @endif
    <div class="collection-heading">
        <div><p class="collection-eyebrow">Gestión operativa</p><h1 class="admin-title">Cobranzas</h1><p class="admin-muted mt-1">Conciliá transferencias, programá hojas y resolvé incidencias de los cobradores.</p></div>
        <button wire:click="showTab('routes')" class="btn admin-action">Programar hoja</button>
    </div>
    <section class="collection-stats" aria-label="Pendientes de gestión">
        <button wire:click="showTab('transfers')" class="collection-stat"><span>Transferencias pendientes</span><strong>{{ $metrics['pending_transfers'] }}</strong><small>Revisión individual</small></button>
        <button wire:click="showTab('routes')" class="collection-stat"><span>Hojas en curso</span><strong>{{ $metrics['routes_in_progress'] }}</strong><small>{{ $metrics['published_routes'] }} programadas</small></button>
        <button wire:click="showTab('incidents')" class="collection-stat"><span>Incidencias abiertas</span><strong>{{ $metrics['open_incidents'] }}</strong><small>Requieren revisión</small></button>
    </section>
    <div class="collection-notice"><strong>Importante:</strong> las declaraciones de cobro quedan pendientes y no reducen la deuda hasta la conciliación administrativa.</div>
    <section class="collection-panel">
        <div class="collection-tabs" role="tablist" aria-label="Áreas de cobranzas">
            <button wire:click="showTab('transfers')" class="{{ $activeTab === 'transfers' ? 'is-active' : '' }}">Transferencias <span>{{ $metrics['pending_transfers'] }}</span></button>
            <button wire:click="showTab('routes')" class="{{ $activeTab === 'routes' ? 'is-active' : '' }}">Hojas</button>
            <button wire:click="showTab('incidents')" class="{{ $activeTab === 'incidents' ? 'is-active' : '' }}">Incidencias <span>{{ $metrics['open_incidents'] }}</span></button>
            <button wire:click="showTab('activity')" class="{{ $activeTab === 'activity' ? 'is-active' : '' }}">Actividad</button>
        </div>

        @if ($activeTab === 'transfers')
            <div class="collection-toolbar"><div><h2>Conciliación de transferencias</h2><p>Revisá cada declaración de transferencia de manera individual.</p></div></div>
            <div class="overflow-x-auto"><table class="table collection-table"><thead><tr><th>Cliente / venta</th><th>Declaración</th><th>Referencia</th><th>Importe</th><th>Estado</th><th></th></tr></thead><tbody>
                @forelse($declarations as $declaration)
                    @php($transfer = $declaration->components->firstWhere('method', 'bank_transfer'))
                    @php($hasCash = $declaration->components->contains('method', 'cash'))
                    <tr><td><strong>{{ $declaration->customer?->last_name }}, {{ $declaration->customer?->first_name }}</strong><small>{{ $declaration->visit?->sale?->sale_number }}</small></td><td>{{ $declaration->declared_at?->format('d/m/Y H:i') }}</td><td>{{ $transfer?->reference ?: 'Sin referencia' }}</td><td class="collection-amount">$ {{ \App\Domain\Commercial\Decimal::display($transfer?->amount ?? '0.00') }}</td><td><span class="collection-badge {{ $declaration->status === 'approved' ? 'is-approved' : '' }}">{{ $declaration->status === 'approved' ? 'Conciliada' : 'Pendiente' }}</span>@if($hasCash)<small>También declara efectivo</small>@endif</td><td>@if($declaration->status === 'pending' && !$hasCash) @can('payments.verify')<button wire:click="approve('{{ $declaration->id }}')" class="btn btn-sm">Conciliar</button>@endcan @endif</td></tr>
                @empty<tr><td colspan="6" class="text-center py-10">No hay transferencias declaradas.</td></tr>@endforelse
            </tbody></table></div><div class="collection-foot">La conciliación se realiza individualmente por transferencia.</div>
            <div class="px-5 pb-4">{{ $declarations->links() }}</div>
        @elseif ($activeTab === 'routes')
            <div class="collection-toolbar"><div><h2>Hojas de cobranza</h2><p>Programá visitas por venta. La hoja solo se inicia cuando el cobrador la confirma desde la aplicación móvil.</p></div></div>
            @can('routes.manage')<form wire:submit="program" class="collection-route-form"><label>Cobrador<select wire:model="collectorMembershipId"><option value="">Elegí un cobrador</option>@foreach($collectors as $collector)<option value="{{ $collector->id }}">{{ $collector->user->name }}</option>@endforeach</select>@error('collectorMembershipId')<small class="text-error">{{ $message }}</small>@enderror</label><label>Fecha<input wire:model="routeDate" type="date">@error('routeDate')<small class="text-error">{{ $message }}</small>@enderror</label><label class="collection-sales">Ventas a visitar<select wire:model="saleIds" multiple size="5">@foreach($sales as $sale)<option value="{{ $sale->id }}">{{ $sale->sale_number }} · {{ $sale->customer->last_name }}, {{ $sale->customer->first_name }} · $ {{ \App\Domain\Commercial\Decimal::display($sale->total_amount) }}</option>@endforeach</select><small>Se pueden programar ventas sin cuotas exigibles.</small>@error('saleIds')<small class="text-error">{{ $message }}</small>@enderror</label><button class="btn admin-action">Programar hoja</button></form>@endcan
            <div class="overflow-x-auto"><table class="table collection-table"><thead><tr><th>Fecha</th><th>Cobrador</th><th>Visitas</th><th>Estado</th></tr></thead><tbody>@forelse($routes as $route)<tr><td>{{ $route->route_date->format('d/m/Y') }}</td><td>{{ $route->collectorMembership?->user?->name }}</td><td>{{ $route->visits_count }}</td><td><span class="collection-badge {{ $route->status === 'in_progress' ? 'is-active' : '' }}">{{ $route->status }}</span></td></tr>@empty<tr><td colspan="4" class="text-center py-8">No hay hojas programadas.</td></tr>@endforelse</tbody></table></div>
        @elseif ($activeTab === 'incidents')
            <div class="collection-toolbar"><div><h2>Incidencias financieras</h2><p>Las evidencias se conservan y las correcciones no reabren hojas cerradas.</p></div></div><div class="collection-list">@forelse($incidents as $incident)<article><div><strong>{{ str_replace('_', ' ', $incident->type) }}</strong><p>{{ $incident->details }}</p><small>{{ $incident->created_at->format('d/m/Y H:i') }}</small></div><span class="collection-badge {{ $incident->status === 'resolved' ? 'is-approved' : '' }}">{{ $incident->status === 'resolved' ? 'Resuelta' : 'Abierta' }}</span></article>@empty<div class="collection-empty">No hay incidencias registradas.</div>@endforelse</div>
        @else
            <div class="collection-toolbar"><div><h2>Últimas acciones</h2><p>Registro auditable de decisiones y operaciones del flujo.</p></div></div><div class="collection-list">@forelse($activity as $event)<article><div><strong>{{ str_replace('_', ' ', $event->action) }}</strong><p>{{ $event->actor?->name ?? 'Sistema' }}</p><small>{{ $event->created_at->format('d/m/Y H:i') }}</small></div></article>@empty<div class="collection-empty">Todavía no hay actividad para mostrar.</div>@endforelse</div>
        @endif
    </section>
</div>

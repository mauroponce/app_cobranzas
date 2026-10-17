<div class="collection-dashboard space-y-5" data-collections-stream-url="{{ route('collections.stream') }}">
    @if (session('status'))
        <div class="alert alert-success" role="status"><span>{{ session('status') }}</span></div>
    @endif

    <div class="collection-heading">
        <div>
            <p class="collection-eyebrow">Gestión operativa</p>
            <h1 class="admin-title">Cobranzas</h1>
            <p class="admin-muted mt-1">Revisá declaraciones, rendiciones, hojas e incidencias con trazabilidad.</p>
        </div>
        @can('routes.manage')
            <button wire:click="showTab('routes')" class="btn admin-action">Programar hoja</button>
        @endcan
    </div>

    <section class="collection-stats" aria-label="Pendientes de gestión">
        <button wire:click="showTab('transfers')" class="collection-stat">
            <span>Declaraciones pendientes</span><strong>{{ $metrics['pending_declarations'] }}</strong><small>Transferencias y efectivo</small>
        </button>
        @can('settlements.view')
            <button wire:click="showTab('settlements')" class="collection-stat">
                <span>Rendiciones pendientes</span><strong>{{ $metrics['pending_settlements'] }}</strong><small>Requieren recepción administrativa</small>
            </button>
        @endcan
        <button wire:click="showTab('routes')" class="collection-stat">
            <span>Hojas en curso</span><strong>{{ $metrics['routes_in_progress'] }}</strong><small>{{ $metrics['published_routes'] }} programadas</small>
        </button>
        <button wire:click="showTab('incidents')" class="collection-stat">
            <span>Incidencias abiertas</span><strong>{{ $metrics['open_incidents'] }}</strong><small>Requieren revisión</small>
        </button>
    </section>

    <div class="collection-notice"><strong>Importante:</strong> las declaraciones no reducen deuda hasta su conciliación; una diferencia queda registrada, nunca se corrige silenciosamente.</div>

    <section class="collection-panel">
        <div class="collection-tabs" role="tablist" aria-label="Áreas de cobranzas">
            <button wire:click="showTab('transfers')" class="{{ $activeTab === 'transfers' ? 'is-active' : '' }}">Declaraciones <span>{{ $metrics['pending_declarations'] }}</span></button>
            @can('settlements.view')
                <button wire:click="showTab('settlements')" class="{{ $activeTab === 'settlements' ? 'is-active' : '' }}">Rendiciones <span>{{ $metrics['pending_settlements'] }}</span></button>
            @endcan
            <button wire:click="showTab('routes')" class="{{ $activeTab === 'routes' ? 'is-active' : '' }}">Hojas</button>
            <button wire:click="showTab('incidents')" class="{{ $activeTab === 'incidents' ? 'is-active' : '' }}">Incidencias <span>{{ $metrics['open_incidents'] }}</span></button>
            <button wire:click="showTab('activity')" class="{{ $activeTab === 'activity' ? 'is-active' : '' }}">Actividad</button>
        </div>

        @if ($activeTab === 'transfers')
            <div class="collection-toolbar">
                <div><h2>Declaraciones de cobro</h2><p>Las transferencias se concilian individualmente; el efectivo queda pendiente de rendición.</p></div>
            </div>
            <div class="overflow-x-auto">
                <table class="table collection-table">
                    <thead><tr><th>Cliente / venta</th><th>Declaración</th><th>Medios / referencia</th><th>Importe</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($declarations as $declaration)
                            @php($transfer = $declaration->components->firstWhere('method', 'bank_transfer'))
                            @php($pendingTransfer = $declaration->components->first(fn ($component) => $component->method === 'bank_transfer' && $component->status === 'pending'))
                            @php($hasCash = $declaration->components->contains('method', 'cash'))
                            <tr>
                                <td><strong>{{ $declaration->customer?->last_name }}, {{ $declaration->customer?->first_name }}</strong><small>{{ $declaration->visit?->sale?->sale_number }}</small></td>
                                <td>{{ $declaration->declared_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ collect($declaration->components)->map(fn ($component) => $component->method === 'bank_transfer' ? 'Transferencia'.($component->reference ? ': '.$component->reference : '') : 'Efectivo')->implode(' · ') }}</td>
                                <td class="collection-amount">$ {{ \App\Domain\Commercial\Decimal::display($declaration->amount) }}</td>
                                <td><span class="collection-badge {{ $declaration->status === 'approved' ? 'is-approved' : '' }}">{{ $declaration->status === 'approved' ? 'Conciliada' : ($hasCash ? 'Pendiente de rendición' : 'Pendiente') }}</span>@if($transfer && $hasCash)<small>Declaración combinada</small>@endif</td>
                                <td>
                                    @if($declaration->status === 'pending' && $pendingTransfer)
                                        @can('payments.verify_transfer')
                                            <button wire:click="approve('{{ $declaration->id }}')" wire:confirm="¿Confirmás que verificaste esta transferencia?" wire:loading.attr="disabled" class="btn btn-sm">Conciliar</button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-10">No hay declaraciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="collection-foot">La conciliación se realiza individualmente por transferencia. El efectivo se recibe durante la rendición.</div>
            <div class="px-5 pb-4">{{ $declarations->links() }}</div>
        @elseif ($activeTab === 'settlements')
            <div class="collection-toolbar">
                <div><h2>Rendiciones de efectivo</h2><p>Compará lo declarado con el efectivo recibido y dejá evidencia de cualquier diferencia.</p></div>
                <label class="collection-inline-filter">Estado
                    <select wire:model.live="settlementStatus">
                        <option value="submitted">Pendientes</option>
                        <option value="all">Todas</option>
                        <option value="approved">Aprobadas</option>
                        <option value="disputed">Con diferencia</option>
                    </select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="table collection-table">
                    <thead><tr><th>Fecha / hoja</th><th>Cobrador</th><th>Declarado</th><th>Recibido</th><th>Diferencia</th><th>Estados</th><th></th></tr></thead>
                    <tbody>
                        @forelse($settlements as $settlement)
                            <tr wire:key="settlement-{{ $settlement->id }}">
                                <td><strong>{{ $settlement->route?->route_date?->format('d/m/Y') ?? 'Sin fecha' }}</strong><small>{{ $settlement->route?->name }} · {{ $settlement->id }}</small></td>
                                <td>{{ $settlement->presenter?->name ?? $settlement->route?->collectorMembership?->user?->name }}</td>
                                <td class="collection-amount">$ {{ \App\Domain\Commercial\Decimal::display($settlement->declared_amount) }}</td>
                                <td class="collection-amount">{{ $settlement->received_amount !== null ? '$ '.\App\Domain\Commercial\Decimal::display($settlement->received_amount) : '—' }}</td>
                                <td class="collection-amount">{{ $this->settlementDifference($settlement) !== null ? '$ '.$this->settlementDifference($settlement) : '—' }}</td>
                                <td><span class="collection-badge {{ $settlement->status === 'approved' ? 'is-approved' : ($settlement->status === 'disputed' ? 'is-error' : '') }}">{{ ['submitted' => 'Rendición pendiente', 'approved' => 'Rendición aprobada', 'disputed' => 'Rendición con diferencia', 'draft' => 'Rendición en borrador'][$settlement->status] ?? $settlement->status }}</span><small>Hoja: {{ ['published' => 'programada', 'in_progress' => 'en curso', 'submitted' => 'presentada', 'completed' => 'completada', 'cancelled' => 'cancelada'][$settlement->route?->status] ?? $settlement->route?->status }}</small></td>
                                <td><button wire:click="reviewSettlement('{{ $settlement->id }}')" class="btn btn-sm btn-ghost">Ver detalle</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-10">No hay rendiciones para el filtro seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($selectedSettlement)
                <section class="collection-detail" aria-labelledby="settlement-title">
                    <div class="collection-detail-heading">
                        <div><p class="collection-eyebrow">Detalle de rendición</p><h3 id="settlement-title">{{ $selectedSettlement->route?->name }}</h3><small>Presentada por {{ $selectedSettlement->presenter?->name }} el {{ $selectedSettlement->submitted_at?->format('d/m/Y H:i') }}</small></div>
                        <button wire:click="closeSettlementReview" class="btn btn-sm btn-ghost" aria-label="Cerrar detalle">Cerrar</button>
                    </div>
                    <div class="collection-summary-grid">
                        <div><span>Declarado</span><strong>$ {{ \App\Domain\Commercial\Decimal::display($selectedSettlement->declared_amount) }}</strong></div>
                        <div><span>Recibido</span><strong>{{ $selectedSettlement->received_amount !== null ? '$ '.\App\Domain\Commercial\Decimal::display($selectedSettlement->received_amount) : 'Pendiente' }}</strong></div>
                        <div><span>Diferencia</span><strong>{{ $this->settlementDifference($selectedSettlement, $selectedSettlement->status === 'submitted' ? $settlementReceivedAmount : null) !== null ? '$ '.$this->settlementDifference($selectedSettlement, $selectedSettlement->status === 'submitted' ? $settlementReceivedAmount : null) : '—' }}</strong></div>
                        <div><span>Estado</span><strong>{{ ['submitted' => 'Pendiente', 'approved' => 'Aprobada', 'disputed' => 'Con diferencia'][$selectedSettlement->status] ?? $selectedSettlement->status }}</strong></div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table collection-table collection-detail-table">
                            <thead><tr><th>Cliente / venta</th><th>Medio / referencia</th><th>Declarado</th><th>Recibido</th></tr></thead>
                            <tbody>
                                @foreach($selectedSettlement->items as $item)
                                    <tr>
                                        <td><strong>{{ $item->component?->declaration?->customer?->last_name }}, {{ $item->component?->declaration?->customer?->first_name }}</strong><small>{{ $item->component?->declaration?->visit?->sale?->sale_number }}</small></td>
                                        <td>{{ $item->component?->method === 'cash' ? 'Efectivo' : 'Transferencia' }}@if($item->component?->reference)<small>{{ $item->component->reference }}</small>@endif</td>
                                        <td class="collection-amount">$ {{ \App\Domain\Commercial\Decimal::display($item->amount) }}</td>
                                        <td class="collection-amount">{{ $item->received_amount !== null ? '$ '.\App\Domain\Commercial\Decimal::display($item->received_amount) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($selectedSettlement->status === 'submitted')
                        @can('settlements.approve')
                            <form wire:submit="approveSettlement" class="collection-review-form">
                                <div class="collection-form-grid">
                                    <label>Efectivo físicamente recibido
                                        <div class="collection-money-input"><span>$</span><input wire:model.live.debounce.300ms="settlementReceivedAmount" inputmode="decimal" autocomplete="off"></div>
                                        @error('settlementReceivedAmount')<small class="text-error">{{ $message }}</small>@enderror
                                    </label>
                                    <label>Observaciones
                                        <textarea wire:model="settlementNotes" rows="3" placeholder="Contexto de la recepción o motivo de la diferencia"></textarea>
                                        @error('settlementNotes')<small class="text-error">{{ $message }}</small>@enderror
                                    </label>
                                </div>
                                @if($this->settlementHasDifference($selectedSettlement))
                                    <div class="collection-warning"><strong>Diferencia detectada.</strong> Atribuí manualmente el efectivo recibido. La suma debe coincidir exactamente.</div>
                                    <div class="collection-attributions">
                                        @foreach($selectedSettlement->items as $item)
                                            <label>
                                                <span>{{ $item->component?->declaration?->customer?->last_name }}, {{ $item->component?->declaration?->customer?->first_name }} · {{ $item->component?->declaration?->visit?->sale?->sale_number }} <small>máx. $ {{ \App\Domain\Commercial\Decimal::display($item->amount) }}</small></span>
                                                <div class="collection-money-input"><span>$</span><input wire:model="settlementAttributions.{{ $item->declaration_component_id }}" inputmode="decimal" autocomplete="off"></div>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('settlementAttributions')<small class="text-error">{{ $message }}</small>@enderror
                                    @error('settlementAttributions.*')<small class="text-error">{{ $message }}</small>@enderror
                                @endif
                                @error('selectedSettlementId')<div class="alert alert-error"><span>{{ $message }}</span></div>@enderror
                                <div class="collection-form-actions">
                                    <button type="button" wire:click="closeSettlementReview" class="btn btn-ghost">Cancelar</button>
                                    <button type="submit" wire:confirm="¿Confirmás la recepción? Esta acción conciliará los componentes y cerrará la hoja." wire:loading.attr="disabled" wire:target="approveSettlement" class="btn admin-action">
                                        <span wire:loading.remove wire:target="approveSettlement">Confirmar recepción</span><span wire:loading wire:target="approveSettlement">Procesando…</span>
                                    </button>
                                </div>
                            </form>
                        @endcan
                    @endif

                    <div class="collection-history">
                        <h4>Historial</h4>
                        @forelse($settlementHistory as $event)
                            <p><strong>{{ str_replace('_', ' ', $event->action) }}</strong><span>{{ $event->actor?->name ?? 'Sistema' }} · {{ $event->created_at?->format('d/m/Y H:i') }}</span></p>
                        @empty
                            <p class="admin-muted">No hay eventos de auditoría para esta rendición.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        @elseif ($activeTab === 'routes')
            <div class="collection-toolbar">
                <div><h2>Hojas de cobranza</h2><p>Consultá sus visitas y reasigná únicamente las que siguen pendientes.</p></div>
            </div>
            @can('routes.manage')
                <form wire:submit="program" class="collection-route-form">
                    <label>Cobrador<select wire:model="collectorMembershipId"><option value="">Elegí un cobrador</option>@foreach($collectors as $collector)<option value="{{ $collector->id }}">{{ $collector->user->name }}</option>@endforeach</select>@error('collectorMembershipId')<small class="text-error">{{ $message }}</small>@enderror</label>
                    <label>Fecha<input wire:model="routeDate" type="date">@error('routeDate')<small class="text-error">{{ $message }}</small>@enderror</label>
                    <label class="collection-sales">Ventas a visitar<select wire:model="saleIds" multiple size="5">@foreach($sales as $sale)<option value="{{ $sale->id }}">{{ $sale->sale_number }} · {{ $sale->customer->last_name }}, {{ $sale->customer->first_name }} · $ {{ \App\Domain\Commercial\Decimal::display($sale->total_amount) }}</option>@endforeach</select><small>Se pueden programar ventas sin cuotas exigibles.</small>@error('saleIds')<small class="text-error">{{ $message }}</small>@enderror</label>
                    <button wire:loading.attr="disabled" class="btn admin-action">Programar hoja</button>
                </form>
            @endcan
            <div class="overflow-x-auto">
                <table class="table collection-table">
                    <thead><tr><th>Fecha</th><th>Cobrador</th><th>Visitas</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($routes as $route)
                            <tr wire:key="route-{{ $route->id }}">
                                <td>{{ $route->route_date->format('d/m/Y') }}</td><td>{{ $route->collectorMembership?->user?->name }}</td><td>{{ $route->visits_count }}</td><td><span class="collection-badge {{ $route->status === 'in_progress' ? 'is-active' : '' }}">{{ ['published' => 'Programada', 'in_progress' => 'En curso', 'submitted' => 'Presentada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'][$route->status] ?? $route->status }}</span></td>
                                <td><button wire:click="showRoute('{{ $route->id }}')" class="btn btn-sm btn-ghost">Ver detalle</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8">No hay hojas programadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($selectedRoute)
                <section class="collection-detail" aria-labelledby="route-title">
                    <div class="collection-detail-heading">
                        <div><p class="collection-eyebrow">Detalle de hoja</p><h3 id="route-title">{{ $selectedRoute->name }}</h3><small>{{ $selectedRoute->route_date->format('d/m/Y') }} · {{ $selectedRoute->collectorMembership?->user?->name }} · versión {{ $selectedRoute->version }}</small></div>
                        <button wire:click="closeRouteDetail" class="btn btn-sm btn-ghost">Cerrar</button>
                    </div>
                    <div class="collection-visits">
                        @forelse($selectedRoute->visits->sortBy('sequence') as $visit)
                            <article wire:key="visit-{{ $visit->id }}" class="{{ $selectedVisitId === $visit->id ? 'is-selected' : '' }}">
                                <div class="collection-visit-sequence">{{ $visit->sequence }}</div>
                                <div class="collection-visit-main"><strong>{{ $visit->customer?->last_name }}, {{ $visit->customer?->first_name }}</strong><small>{{ $visit->sale?->sale_number }} · saldo actual $ {{ $this->outstandingAmount($visit->sale) }}</small></div>
                                <div><span class="collection-badge {{ $visit->status === 'pending' ? 'is-active' : '' }}">{{ ['pending' => 'Pendiente', 'recorded' => 'Registrada', 'reassigned' => 'Reasignada', 'cancelled' => 'Cancelada', 'closed' => 'Cerrada'][$visit->status] ?? $visit->status }}</span></div>
                                @if($visit->status === 'pending')
                                    @can('collections.reassign')<button wire:click="beginReassignment('{{ $visit->id }}')" class="btn btn-sm">Reasignar</button>@endcan
                                @endif
                            </article>
                        @empty
                            <div class="collection-empty">La hoja no contiene visitas.</div>
                        @endforelse
                    </div>

                    @if($selectedVisitId)
                        @php($sourceVisit = $selectedRoute->visits->firstWhere('id', $selectedVisitId))
                        <form wire:submit="reassignVisit" class="collection-reassignment-form">
                            <div><h4>Reasignar visita</h4><p>{{ $sourceVisit?->customer?->last_name }}, {{ $sourceVisit?->customer?->first_name }} · {{ $sourceVisit?->sale?->sale_number }} · cobrador actual: {{ $selectedRoute->collectorMembership?->user?->name }}</p></div>
                            <label>Cobrador de destino
                                <select wire:model="destinationCollectorMembershipId">
                                    <option value="">Elegí un cobrador activo</option>
                                    @foreach($collectors->where('id', '!=', $selectedRoute->collector_membership_id) as $collector)<option value="{{ $collector->id }}">{{ $collector->user->name }}</option>@endforeach
                                </select>
                                @error('destinationCollectorMembershipId')<small class="text-error">{{ $message }}</small>@enderror
                            </label>
                            <label>Motivo
                                <textarea wire:model="reassignmentReason" rows="3" placeholder="Motivo operativo de la reasignación"></textarea>
                                @error('reassignmentReason')<small class="text-error">{{ $message }}</small>@enderror
                            </label>
                            @error('selectedVisitId')<div class="alert alert-error"><span>{{ $message }}</span></div>@enderror
                            <div class="collection-form-actions">
                                <button type="button" wire:click="cancelReassignment" class="btn btn-ghost">Cancelar</button>
                                <button type="submit" wire:confirm="¿Confirmás la reasignación? La visita original conservará su historial." wire:loading.attr="disabled" wire:target="reassignVisit" class="btn admin-action"><span wire:loading.remove wire:target="reassignVisit">Confirmar reasignación</span><span wire:loading wire:target="reassignVisit">Procesando…</span></button>
                            </div>
                        </form>
                    @endif

                    <div class="collection-history">
                        <h4>Historial de reasignaciones</h4>
                        @forelse($routeReassignments as $reassignment)
                            <p><strong>{{ $reassignment->sourceVisit?->customer?->last_name }}, {{ $reassignment->sourceVisit?->customer?->first_name }}</strong><span>{{ $reassignment->fromCollector?->user?->name }} → {{ $reassignment->toCollector?->user?->name }} · {{ $reassignment->reason }} · {{ $reassignment->created_at?->format('d/m/Y H:i') }}</span></p>
                        @empty
                            <p class="admin-muted">Esta hoja no tiene reasignaciones registradas.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        @elseif ($activeTab === 'incidents')
            <div class="collection-toolbar"><div><h2>Incidencias financieras</h2><p>Vista de solo lectura con contexto de hoja, visita, venta y conciliación relacionada.</p></div></div>
            <div class="collection-list collection-incidents">
                @forelse($incidents as $incident)
                    <article wire:key="incident-{{ $incident->id }}">
                        <div class="collection-incident-main">
                            <div><strong>{{ ['cash_difference' => 'Diferencia de efectivo', 'financial_sync_conflict' => 'Conflicto de sincronización'][$incident->type] ?? str_replace('_', ' ', $incident->type) }}</strong><span class="collection-badge {{ $incident->status === 'resolved' ? 'is-approved' : 'is-error' }}">{{ $incident->status === 'resolved' ? 'Resuelta' : 'Abierta' }}</span></div>
                            <p>{{ $incident->details }}</p>
                            <dl>
                                <div><dt>Fecha</dt><dd>{{ $incident->created_at->format('d/m/Y H:i') }}</dd></div>
                                <div><dt>Reportó</dt><dd>{{ $incident->reporter?->name ?? 'Sistema' }}</dd></div>
                                @if($incident->route)<div><dt>Hoja</dt><dd>{{ $incident->route->name }} · {{ $incident->route->collectorMembership?->user?->name }}</dd></div>@endif
                                @if($incident->visit)<div><dt>Visita</dt><dd>{{ $incident->visit->customer?->last_name }}, {{ $incident->visit->customer?->first_name }} · {{ $incident->visit->sale?->sale_number }}</dd></div>@endif
                                @if($incident->declaration)<div><dt>Declaración</dt><dd>{{ $incident->declaration->declared_at?->format('d/m/Y H:i') }} · $ {{ \App\Domain\Commercial\Decimal::display($incident->declaration->amount) }}</dd></div>@endif
                                @if($incident->route?->settlement)<div><dt>Rendición</dt><dd>{{ ['submitted' => 'Pendiente', 'approved' => 'Aprobada', 'disputed' => 'Con diferencia'][$incident->route->settlement->status] ?? $incident->route->settlement->status }} · recibido $ {{ \App\Domain\Commercial\Decimal::display($incident->route->settlement->received_amount ?? '0.00') }}</dd></div>@endif
                                @if($incident->route?->settlement?->items?->isNotEmpty())
                                    <div><dt>Clientes / ventas</dt><dd>{{ $incident->route->settlement->items->map(fn ($item) => trim(($item->component?->declaration?->customer?->last_name ?? '').', '.($item->component?->declaration?->customer?->first_name ?? '')).' · '.($item->component?->declaration?->visit?->sale?->sale_number ?? ''))->unique()->implode(' · ') }}</dd></div>
                                @endif
                                @if($incident->resolver)<div><dt>Resolución</dt><dd>{{ $incident->resolver->name }} · {{ $incident->resolved_at?->format('d/m/Y H:i') }}</dd></div>@endif
                            </dl>
                        </div>
                    </article>
                @empty
                    <div class="collection-empty">No hay incidencias registradas.</div>
                @endforelse
            </div>
        @else
            <div class="collection-toolbar"><div><h2>Últimas acciones</h2><p>Registro auditable de decisiones y operaciones del flujo.</p></div></div>
            <div class="collection-list">
                @forelse($activity as $event)<article><div><strong>{{ str_replace('_', ' ', $event->action) }}</strong><p>{{ $event->actor?->name ?? 'Sistema' }}</p><small>{{ $event->created_at->format('d/m/Y H:i') }}</small></div></article>@empty<div class="collection-empty">Todavía no hay actividad para mostrar.</div>@endforelse
            </div>
        @endif
    </section>
</div>

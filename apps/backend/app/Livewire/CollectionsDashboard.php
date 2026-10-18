<?php

namespace App\Livewire;

use App\Domain\Collections\CashSettlementScopeService;
use App\Domain\Collections\CashSettlementService;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Collections\PaymentService;
use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\LivewireTenantContext;
use App\Livewire\Concerns\ResolvesTenantContext;
use App\Models\AuditEvent;
use App\Models\CashSettlement;
use App\Models\CollectionDeclaration;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionIncident;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\CollectionVisitReassignment;
use App\Models\Sale;
use App\Models\TenantMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CollectionsDashboard extends Component
{
    use ResolvesTenantContext { mount as resolveTenantContext; }

    public string $activeTab = 'transfers';

    public string $collectorMembershipId = '';

    public string $routeDate = '';

    /** @var list<string> */
    public array $saleIds = [];

    public string $settlementStatus = 'submitted';

    public ?string $selectedSettlementId = null;

    public string $settlementReceivedAmount = '';

    public string $settlementNotes = '';

    /** @var array<string, string> */
    public array $settlementAttributions = [];

    public ?string $selectedRouteId = null;

    public ?string $selectedVisitId = null;

    public string $destinationCollectorMembershipId = '';

    public string $reassignmentReason = '';

    public function mount(): void
    {
        $this->resolveTenantContext();
        $membership = app(LivewireTenantContext::class)->resolve(request(), auth()->user(), $this->tenantId);
        $this->routeDate = today($membership->tenant->timezone)->toDateString();
    }

    public function program(CollectionRouteService $routes): void
    {
        Gate::authorize('routes.manage');
        $data = $this->validate([
            'collectorMembershipId' => ['required', 'uuid'],
            'routeDate' => ['required', 'date'],
            'saleIds' => ['required', 'array', 'min:1'],
            'saleIds.*' => ['uuid', 'distinct'],
        ]);
        $route = $routes->program(request()->attributes->get('activeTenant'), auth()->user(), $data['collectorMembershipId'], $data['routeDate'], $data['saleIds']);
        $this->reset('collectorMembershipId', 'saleIds');
        session()->flash('status', 'La hoja fue programada con '.$route->visits->count().' visita(s).');
    }

    public function showTab(string $tab): void
    {
        abort_unless(in_array($tab, ['transfers', 'settlements', 'routes', 'incidents', 'activity'], true), 404);
        if ($tab === 'settlements') {
            Gate::authorize('settlements.view');
        }
        if ($tab === 'routes') {
            Gate::authorize('routes.view');
        }

        $this->activeTab = $tab;
    }

    public function approve(string $declarationId, PaymentService $payments): void
    {
        Gate::authorize('payments.verify_transfer');
        $tenant = request()->attributes->get('activeTenant');
        $component = CollectionDeclarationComponent::forTenant($tenant)
            ->where('declaration_id', $declarationId)
            ->where('method', 'bank_transfer')
            ->where('status', 'pending')
            ->firstOrFail();
        $payments->confirmTransferComponent($tenant, auth()->user(), $component);
        session()->flash('status', 'La transferencia fue conciliada y aplicada a la cuenta corriente.');
    }

    public function reviewSettlement(string $settlementId, CashSettlementScopeService $scope): void
    {
        Gate::authorize('settlements.view');
        $settlement = $scope->settlements(
            request()->attributes->get('activeTenant'),
            request()->attributes->get('activeMembership'),
        )->with('items')->findOrFail($settlementId);

        $this->resetValidation();
        $this->selectedSettlementId = $settlement->id;
        $this->settlementReceivedAmount = $settlement->received_amount ?? $settlement->declared_amount;
        $this->settlementNotes = $settlement->notes ?? '';
        $this->settlementAttributions = $settlement->items
            ->mapWithKeys(fn ($item): array => [$item->declaration_component_id => $item->received_amount ?? $item->amount])
            ->all();
    }

    public function closeSettlementReview(): void
    {
        $this->reset('selectedSettlementId', 'settlementReceivedAmount', 'settlementNotes', 'settlementAttributions');
        $this->resetValidation();
    }

    public function approveSettlement(CashSettlementService $settlements): void
    {
        Gate::authorize('settlements.approve');
        $data = $this->validate([
            'selectedSettlementId' => ['required', 'uuid'],
            'settlementReceivedAmount' => ['required', 'regex:/^\d{1,12}([\.,]\d{1,2})?$/'],
            'settlementNotes' => ['nullable', 'string', 'max:2000'],
            'settlementAttributions' => ['array'],
            'settlementAttributions.*' => ['nullable', 'regex:/^\d{1,12}([\.,]\d{1,2})?$/'],
        ], [
            'settlementReceivedAmount.required' => 'Ingresá el efectivo físicamente recibido.',
            'settlementReceivedAmount.regex' => 'Ingresá un importe válido con hasta dos decimales.',
            'settlementAttributions.*.regex' => 'Cada atribución debe ser un importe válido con hasta dos decimales.',
        ]);
        $tenant = request()->attributes->get('activeTenant');
        $settlement = CashSettlement::forTenant($tenant)->findOrFail($data['selectedSettlementId']);
        $attributions = collect($data['settlementAttributions'])
            ->map(fn ($amount): string => $this->normalizeMoney((string) ($amount ?: '0')))
            ->all();

        try {
            $result = $settlements->approve(
                $tenant,
                auth()->user(),
                $settlement,
                $this->normalizeMoney($data['settlementReceivedAmount']),
                $attributions,
                filled($data['settlementNotes']) ? $data['settlementNotes'] : null,
            );
        } catch (ValidationException $exception) {
            $this->addMappedErrors($exception, [
                'settlement' => 'selectedSettlementId',
                'received_amount' => 'settlementReceivedAmount',
                'attributions' => 'settlementAttributions',
            ]);

            return;
        }

        $this->closeSettlementReview();
        session()->flash('status', $result->status === 'approved'
            ? 'La rendición fue aprobada sin diferencias.'
            : 'La rendición fue conciliada con diferencia y quedó registrada para revisión.');
    }

    public function showRoute(string $routeId): void
    {
        Gate::authorize('routes.view');
        $route = CollectionRoute::forTenant(request()->attributes->get('activeTenant'))->findOrFail($routeId);
        $this->selectedRouteId = $route->id;
        $this->reset('selectedVisitId', 'destinationCollectorMembershipId', 'reassignmentReason');
        $this->resetValidation();
    }

    public function closeRouteDetail(): void
    {
        $this->reset('selectedRouteId', 'selectedVisitId', 'destinationCollectorMembershipId', 'reassignmentReason');
        $this->resetValidation();
    }

    public function beginReassignment(string $visitId): void
    {
        Gate::authorize('collections.reassign');
        $visit = CollectionVisit::forTenant(request()->attributes->get('activeTenant'))->findOrFail($visitId);
        if ($visit->status !== 'pending') {
            throw ValidationException::withMessages(['selectedVisitId' => 'Solo se pueden reasignar visitas pendientes.']);
        }

        $this->resetValidation();
        $this->selectedRouteId = $visit->route_id;
        $this->selectedVisitId = $visit->id;
        $this->reset('destinationCollectorMembershipId', 'reassignmentReason');
    }

    public function cancelReassignment(): void
    {
        $this->reset('selectedVisitId', 'destinationCollectorMembershipId', 'reassignmentReason');
        $this->resetValidation();
    }

    public function reassignVisit(CollectionRouteService $routes): void
    {
        Gate::authorize('collections.reassign');
        $data = $this->validate([
            'selectedVisitId' => ['required', 'uuid'],
            'destinationCollectorMembershipId' => ['required', 'uuid'],
            'reassignmentReason' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'destinationCollectorMembershipId.required' => 'Elegí el cobrador de destino.',
            'reassignmentReason.required' => 'Indicá el motivo de la reasignación.',
            'reassignmentReason.min' => 'El motivo debe tener al menos tres caracteres.',
        ]);
        $tenant = request()->attributes->get('activeTenant');
        $visit = CollectionVisit::forTenant($tenant)->findOrFail($data['selectedVisitId']);

        try {
            $targetVisit = $routes->reassignVisit($tenant, auth()->user(), $visit, $data['destinationCollectorMembershipId'], $data['reassignmentReason']);
        } catch (ValidationException $exception) {
            $this->addMappedErrors($exception, [
                'visit' => 'selectedVisitId',
                'collector_membership_id' => 'destinationCollectorMembershipId',
            ]);

            return;
        }

        $this->selectedRouteId = $targetVisit->route_id;
        $this->cancelReassignment();
        session()->flash('status', 'La visita fue reasignada y la trazabilidad quedó registrada.');
    }

    public function settlementHasDifference(CashSettlement $settlement): bool
    {
        if ($this->settlementReceivedAmount === '') {
            return false;
        }

        try {
            return Decimal::toInteger($this->normalizeMoney($this->settlementReceivedAmount)) !== Decimal::toInteger($settlement->declared_amount);
        } catch (ValidationException) {
            return false;
        }
    }

    public function settlementDifference(CashSettlement $settlement, ?string $receivedAmount = null): ?string
    {
        $receivedAmount ??= $settlement->received_amount;
        if ($receivedAmount === null || $receivedAmount === '') {
            return null;
        }

        try {
            $difference = Decimal::toInteger($settlement->declared_amount) - Decimal::toInteger($this->normalizeMoney($receivedAmount));

            return $difference >= 0 ? Decimal::display(Decimal::format($difference)) : null;
        } catch (ValidationException) {
            return null;
        }
    }

    public function outstandingAmount(Sale $sale): string
    {
        $total = 0;
        foreach ($sale->accountEntries->where('status', 'open') as $entry) {
            $total += Decimal::toInteger($entry->outstanding_amount);
        }

        return Decimal::display(Decimal::format($total));
    }

    public function render(): View
    {
        $membership = request()->attributes->get('activeMembership');
        $settlementScope = app(CashSettlementScopeService::class);
        Gate::authorize('payments.view');
        $tenantId = $membership->tenant_id;
        $declarations = CollectionDeclaration::forTenant($tenantId);
        $pendingDeclarations = (clone $declarations)->where('status', 'pending')->count();
        $routes = CollectionRoute::forTenant($tenantId)->with(['collectorMembership.user'])->withCount('visits')->latest('route_date')->limit(20)->get();
        $openIncidents = CollectionIncident::forTenant($tenantId)->where('status', 'open')->count();
        $pendingSettlements = Gate::allows('settlements.view')
            ? $settlementScope->settlements($membership->tenant, $membership)->where('status', 'submitted')->count()
            : 0;

        $settlements = collect();
        $selectedSettlement = null;
        $settlementHistory = collect();
        if (Gate::allows('settlements.view')) {
            $settlementQuery = $settlementScope->settlements($membership->tenant, $membership)
                ->with(['route.collectorMembership.user', 'presenter', 'reviewer'])
                ->latest('submitted_at');
            if ($this->settlementStatus !== 'all') {
                $settlementQuery->where('status', $this->settlementStatus);
            }
            $settlements = $settlementQuery->limit(50)->get();

            if ($this->selectedSettlementId) {
                $selectedSettlement = $settlementScope->settlements($membership->tenant, $membership)
                    ->with(['route.collectorMembership.user', 'presenter', 'reviewer', 'items.component.declaration.customer', 'items.component.declaration.visit.sale'])
                    ->findOrFail($this->selectedSettlementId);
                $settlementHistory = AuditEvent::query()->where('tenant_id', $tenantId)
                    ->where('subject_type', CashSettlement::class)->where('subject_id', $selectedSettlement->id)
                    ->with('actor')->latest()->get();
            }
        }

        $selectedRoute = null;
        $routeReassignments = collect();
        if ($this->selectedRouteId) {
            $selectedRoute = CollectionRoute::forTenant($tenantId)
                ->with(['collectorMembership.user', 'visits.customer', 'visits.sale.accountEntries'])
                ->findOrFail($this->selectedRouteId);
            $routeReassignments = CollectionVisitReassignment::forTenant($tenantId)
                ->where(fn ($query) => $query->where('from_route_id', $selectedRoute->id)->orWhere('to_route_id', $selectedRoute->id))
                ->with(['sourceVisit.customer', 'targetVisit.customer', 'fromCollector.user', 'toCollector.user', 'actor'])
                ->latest()->get();
        }

        return view('livewire.collections-dashboard', [
            'declarations' => $declarations->with(['customer', 'components', 'visit.sale'])->latest('declared_at')->paginate(20),
            'collectors' => TenantMembership::forTenant($tenantId)->with('user')->where('role', 'collector')->where('status', 'active')->orderBy('joined_at')->get(),
            'sales' => Sale::forTenant($tenantId)->with('customer')->where('status', 'confirmed')->latest('confirmed_at')->limit(100)->get(),
            'routes' => $routes,
            'settlements' => $settlements,
            'selectedSettlement' => $selectedSettlement,
            'settlementHistory' => $settlementHistory,
            'selectedRoute' => $selectedRoute,
            'routeReassignments' => $routeReassignments,
            'incidents' => CollectionIncident::forTenant($tenantId)
                ->with(['route.collectorMembership.user', 'route.settlement.items.component.declaration.customer', 'route.settlement.items.component.declaration.visit.sale', 'visit.customer', 'visit.sale', 'declaration', 'reporter', 'resolver'])
                ->latest()->limit(50)->get(),
            'activity' => AuditEvent::query()->where('tenant_id', $tenantId)->with('actor')->latest()->limit(20)->get(),
            'metrics' => [
                'pending_declarations' => $pendingDeclarations,
                'pending_settlements' => $pendingSettlements,
                'routes_in_progress' => $routes->where('status', 'in_progress')->count(),
                'published_routes' => $routes->where('status', 'published')->count(),
                'open_incidents' => $openIncidents,
            ],
        ]);
    }

    private function normalizeMoney(string $amount): string
    {
        return str_replace(',', '.', trim($amount));
    }

    /** @param array<string, string> $mapping */
    private function addMappedErrors(ValidationException $exception, array $mapping): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($mapping[$field] ?? $field, $message);
            }
        }
    }
}

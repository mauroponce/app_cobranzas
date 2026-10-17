<?php

namespace App\Livewire;

use App\Domain\Collections\CollectionRouteService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\LivewireTenantContext;
use App\Livewire\Concerns\ResolvesTenantContext;
use App\Models\AuditEvent;
use App\Models\CollectionDeclaration;
use App\Models\CollectionIncident;
use App\Models\CollectionRoute;
use App\Models\Sale;
use App\Models\TenantMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CollectionsDashboard extends Component
{
    use ResolvesTenantContext { mount as resolveTenantContext; }

    public string $activeTab = 'transfers';

    public string $collectorMembershipId = '';

    public string $routeDate = '';

    /** @var list<string> */
    public array $saleIds = [];

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
        abort_unless(in_array($tab, ['transfers', 'routes', 'incidents', 'activity'], true), 404);
        $this->activeTab = $tab;
    }

    public function approve(string $declarationId, PaymentService $payments): void
    {
        Gate::authorize('payments.verify');
        $payments->approveDeclaration(request()->attributes->get('activeTenant'), auth()->user(), CollectionDeclaration::findOrFail($declarationId));
        session()->flash('status', 'La declaración fue conciliada y aplicada a la cuenta corriente.');
    }

    public function render(): View
    {
        $membership = request()->attributes->get('activeMembership');
        Gate::authorize('payments.view');
        $tenantId = $membership->tenant_id;
        $declarations = CollectionDeclaration::forTenant($tenantId);
        $pendingDeclarations = (clone $declarations)->where('status', 'pending')->count();
        $routes = CollectionRoute::forTenant($tenantId)->with(['collectorMembership.user'])->withCount('visits')->latest('route_date')->limit(20)->get();
        $openIncidents = CollectionIncident::forTenant($tenantId)->where('status', 'open')->count();

        return view('livewire.collections-dashboard', [
            'declarations' => $declarations->with(['customer', 'components', 'visit.sale'])->latest('declared_at')->paginate(20),
            'collectors' => TenantMembership::forTenant($membership->tenant_id)->with('user')->where('role', 'collector')->where('status', 'active')->orderBy('joined_at')->get(),
            'sales' => Sale::forTenant($membership->tenant_id)->with('customer')->where('status', 'confirmed')->latest('confirmed_at')->limit(100)->get(),
            'routes' => $routes,
            'incidents' => CollectionIncident::forTenant($tenantId)->latest()->limit(20)->get(),
            'activity' => AuditEvent::query()->where('tenant_id', $tenantId)->with('actor')->latest()->limit(20)->get(),
            'metrics' => ['pending_declarations' => $pendingDeclarations, 'routes_in_progress' => $routes->where('status', 'in_progress')->count(), 'published_routes' => $routes->where('status', 'published')->count(), 'open_incidents' => $openIncidents],
        ]);
    }
}

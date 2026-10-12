<?php

namespace App\Livewire;

use App\Domain\Collections\CollectionAssignmentService;
use App\Livewire\Concerns\ResolvesTenantContext;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\TenantMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class CollectionPortfolioManager extends Component
{
    use ResolvesTenantContext;
    use WithPagination;

    public string $search = '';

    public string $collectorId = '';

    public string $zoneId = '';

    public string $assignmentStatus = 'all';

    public string $customerId = '';

    public string $newCollectorId = '';

    public string $reason = '';

    public bool $confirming = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCollectorId(): void
    {
        $this->resetPage();
    }

    public function updatingZoneId(): void
    {
        $this->resetPage();
    }

    public function updatingAssignmentStatus(): void
    {
        $this->resetPage();
    }

    public function beginAssignment(string $customerId): void
    {
        Gate::authorize('customers.assign_collector');
        $this->reset(['newCollectorId', 'reason']);
        $this->customerId = $customerId;
        $this->confirming = true;
    }

    public function saveAssignment(CollectionAssignmentService $assignments): void
    {
        Gate::authorize('customers.assign_collector');
        $data = $this->validate(['customerId' => ['required', 'uuid'], 'newCollectorId' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $tenant = request()->attributes->get('activeTenant');
        $customer = Customer::forTenant($tenant)->whereKey($data['customerId'])->firstOrFail();
        $collector = TenantMembership::forTenant($tenant)->whereKey($data['newCollectorId'])->firstOrFail();
        $assignments->assign($tenant, auth()->user(), $customer, $collector, $data['reason'] ?: null);
        $this->confirming = false;
        session()->flash('status', 'La cartera fue actualizada.');
    }

    public function removeAssignment(string $customerId, CollectionAssignmentService $assignments): void
    {
        Gate::authorize('customers.unassign_collector');
        $tenant = request()->attributes->get('activeTenant');
        $assignments->unassign($tenant, auth()->user(), Customer::forTenant($tenant)->whereKey($customerId)->firstOrFail(), 'Desasignado desde el panel');
        session()->flash('status', 'El cliente quedó sin cobrador asignado.');
    }

    public function render(): View
    {
        Gate::authorize('customers.view_assignments');
        $tenant = request()->attributes->get('activeTenant');
        $activeAssignment = fn ($query) => $query->whereNull('unassigned_at');
        $customers = Customer::forTenant($tenant)->with(['collectorAssignments' => $activeAssignment, 'collectorAssignments.collectorMembership.user', 'zoneAssignments' => $activeAssignment, 'zoneAssignments.zone'])
            ->withSum(['accountEntries as outstanding_amount' => fn ($query) => $query->where('status', 'open')], 'outstanding_amount')
            ->when($this->search, fn ($query) => $query->where(fn ($inner) => $inner->where('first_name', 'ilike', "%{$this->search}%")->orWhere('last_name', 'ilike', "%{$this->search}%")->orWhere('document_number', 'ilike', "%{$this->search}%")->orWhere('customer_number', 'ilike', "%{$this->search}%")))
            ->when($this->collectorId, fn ($query) => $query->whereHas('collectorAssignments', fn ($inner) => $inner->whereNull('unassigned_at')->where('collector_membership_id', $this->collectorId)))
            ->when($this->zoneId, fn ($query) => $query->whereHas('zoneAssignments', fn ($inner) => $inner->whereNull('unassigned_at')->where('zone_id', $this->zoneId)))
            ->when($this->assignmentStatus === 'assigned', fn ($query) => $query->whereHas('collectorAssignments', $activeAssignment))
            ->when($this->assignmentStatus === 'unassigned', fn ($query) => $query->whereDoesntHave('collectorAssignments', $activeAssignment))
            ->orderBy('last_name')->paginate(20);
        $history = $this->customerId ? Customer::forTenant($tenant)->whereKey($this->customerId)->first()?->collectorAssignments()->with(['collectorMembership.user', 'assignedByMembership.user'])->latest('assigned_at')->get() : collect();

        return view('livewire.collection-portfolio-manager', ['customers' => $customers, 'collectors' => TenantMembership::forTenant($tenant)->with('user')->where('status', 'active')->orderBy('role')->get(), 'zones' => CollectionZone::forTenant($tenant)->where('status', 'active')->orderBy('name')->get(), 'history' => $history]);
    }
}

<?php

namespace App\Livewire;

use App\Domain\Collections\PaymentService;
use App\Models\CollectionDeclaration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CollectionsDashboard extends Component
{
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
        return view('livewire.collections-dashboard', ['declarations' => CollectionDeclaration::forTenant($membership->tenant_id)->latest('declared_at')->paginate(20)]);
    }
}

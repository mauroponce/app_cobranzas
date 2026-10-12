<?php

namespace App\Livewire;

use App\Domain\Collections\CollectionPortfolioService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CollectionsDashboard extends Component
{
    public string $customerId = '';

    public string $amount = '';

    public string $method = 'cash';

    public string $reference = '';

    public string $notes = '';

    public function register(PaymentService $payments): void
    {
        Gate::authorize('payments.create');
        $data = $this->validate(['customerId' => ['required', 'uuid'], 'amount' => ['required', 'string', 'max:32'], 'method' => ['required', 'in:cash,bank_transfer'], 'reference' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $tenant = request()->attributes->get('activeTenant');
        $payment = $data['method'] === 'cash'
            ? $payments->registerCash($tenant, auth()->user(), ['customer_id' => $data['customerId'], 'amount' => $data['amount'], 'reference' => $data['reference'], 'notes' => $data['notes'], 'source' => 'dashboard'])
            : $payments->declareTransfer($tenant, auth()->user(), ['customer_id' => $data['customerId'], 'amount' => $data['amount'], 'reference' => $data['reference'], 'notes' => $data['notes'], 'source' => 'dashboard']);
        $this->reset(['customerId', 'amount', 'reference', 'notes']);
        session()->flash('status', $payment->method === 'cash' ? 'El efectivo fue registrado e imputado.' : 'La transferencia quedó pendiente de verificación.');
    }

    public function verify(string $paymentId, PaymentService $payments): void
    {
        Gate::authorize('payments.verify_transfer');
        $payments->confirmTransfer(request()->attributes->get('activeTenant'), auth()->user(), Payment::findOrFail($paymentId));
    }

    public function render(AuthorizationService $authorization, CollectionPortfolioService $portfolio): View
    {
        $membership = request()->attributes->get('activeMembership');
        Gate::authorize('payments.view');
        $payments = Payment::forTenant($membership->tenant_id)->latest('received_at');
        $paymentScope = $authorization->decision($membership, 'payments.view')['scope'];
        if ($paymentScope === 'own') {
            $payments->where('received_by_user_id', auth()->id());
        } elseif (in_array($paymentScope, ['assigned', 'team'], true)) {
            $payments->whereIn('customer_id', $portfolio->customers($membership->tenant, $membership, 'payments.view')->select('customers.id'));
        }

        return view('livewire.collections-dashboard', ['payments' => $payments->paginate(20), 'customers' => $portfolio->customers($membership->tenant, $membership)->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])]);
    }
}

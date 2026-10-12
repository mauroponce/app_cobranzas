<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionPortfolioService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApiController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization, CollectionPortfolioService $portfolio): JsonResponse
    {
        $membership = $request->attributes->get('activeMembership');
        abort_unless($authorization->allows($membership, 'payments.view'), 403);
        $decision = $authorization->decision($membership, 'payments.view');
        $query = Payment::forTenant($membership->tenant_id)->orderByDesc('received_at');
        if ($decision['scope'] === 'own') {
            $query->where('received_by_user_id', $request->user()->id);
        } elseif (in_array($decision['scope'], ['assigned', 'team'], true)) {
            $query->whereIn('customer_id', $portfolio->customers($request->attributes->get('activeTenant'), $membership, 'payments.view')->select('customers.id'));
        }
        $rows = $query->paginate(30);

        return response()->json(['data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()]]);
    }

    public function show(Request $request, Payment $payment, AuthorizationService $authorization, CollectionPortfolioService $portfolio): JsonResponse
    {
        $membership = $request->attributes->get('activeMembership');
        abort_unless($payment->tenant_id === $membership->tenant_id && $authorization->allows($membership, 'payments.view'), 404);
        $decision = $authorization->decision($membership, 'payments.view');
        abort_unless($decision['scope'] !== 'own' || $payment->received_by_user_id === $request->user()->id, 403);
        if (in_array($decision['scope'], ['assigned', 'team'], true)) {
            abort_unless($portfolio->customers($request->attributes->get('activeTenant'), $membership, 'payments.view')->whereKey($payment->customer_id)->exists(), 403);
        }

        return response()->json(['data' => $payment->only(['id', 'customer_id', 'method', 'source', 'status', 'amount', 'unallocated_amount', 'received_at', 'confirmed_at', 'reference', 'notes'])]);
    }

    public function store(Request $request, PaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid'], 'amount' => ['required', 'string', 'max:32'], 'method' => ['required', 'in:cash,bank_transfer'],
            'allocations' => ['nullable', 'array'], 'allocations.*.account_entry_id' => ['required_with:allocations', 'uuid'], 'allocations.*.amount' => ['nullable', 'string', 'max:32'],
            'received_at' => ['nullable', 'date'], 'reference' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $tenant = $request->attributes->get('activeTenant');
        $payment = $data['method'] === 'cash' ? $payments->registerCash($tenant, $request->user(), $data + ['source' => 'dashboard']) : $payments->declareTransfer($tenant, $request->user(), $data + ['source' => 'dashboard']);

        return response()->json(['data' => $payment], 201);
    }

    public function verifyTransfer(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        return response()->json(['data' => $payments->confirmTransfer($request->attributes->get('activeTenant'), $request->user(), $payment)]);
    }

    public function reverse(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json(['data' => $payments->reverse($request->attributes->get('activeTenant'), $request->user(), $payment, $data['reason'])]);
    }
}

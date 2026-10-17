<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CashSettlementService;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CashSettlement;
use App\Models\CollectionRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSettlementApiController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $membership = $request->attributes->get('activeMembership');
        abort_unless($authorization->allows($membership, 'settlements.view'), 403);
        $query = CashSettlement::forTenant($membership->tenant_id)->latest();
        if ($authorization->decision($membership, 'settlements.view')['scope'] === 'own') {
            $query->where('collector_id', $request->user()->id);
        }

        return response()->json(['data' => $query->paginate(30)]);
    }

    public function store(Request $request, CashSettlementService $service): JsonResponse
    {
        $data = $request->validate(['route_id' => ['required', 'uuid'], 'local_queue_empty' => ['required', 'accepted'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $route = CollectionRoute::findOrFail($data['route_id']);

        return response()->json(['data' => $service->createForRoute($request->attributes->get('activeTenant'), $request->user(), $route, true, $data['notes'] ?? null)], 201);
    }

    public function submit(Request $request, CashSettlement $settlement, CashSettlementService $service): JsonResponse
    {
        return response()->json(['data' => $service->submit($request->attributes->get('activeTenant'), $request->user(), $settlement)]);
    }

    public function approve(Request $request, CashSettlement $settlement, CashSettlementService $service): JsonResponse
    {
        $data = $request->validate(['received_amount' => ['required', 'string'], 'attributions' => ['sometimes', 'array'], 'attributions.*' => ['string'], 'notes' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $service->approve($request->attributes->get('activeTenant'), $request->user(), $settlement, $data['received_amount'], $data['attributions'] ?? [], $data['notes'] ?? null)]);
    }
}

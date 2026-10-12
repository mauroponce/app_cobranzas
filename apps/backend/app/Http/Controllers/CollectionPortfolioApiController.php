<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\CollectionPortfolioService;
use App\Models\Customer;
use App\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionPortfolioApiController extends Controller
{
    public function index(Request $request, CollectionPortfolioService $portfolio): JsonResponse
    {
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $customers = $portfolio->customers($tenant, $membership)->orderBy('last_name')->orderBy('first_name')->paginate(50);

        return response()->json(['data' => $customers]);
    }

    public function assign(Request $request, Customer $customer, CollectionAssignmentService $assignments): JsonResponse
    {
        $tenant = $request->attributes->get('activeTenant');
        $data = $request->validate(['collector_membership_id' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $collector = TenantMembership::forTenant($tenant)->whereKey($data['collector_membership_id'])->firstOrFail();
        $assignment = $assignments->assign($tenant, $request->user(), $customer, $collector, $data['reason'] ?? null);

        return response()->json(['data' => $assignment], 201);
    }

    public function unassign(Request $request, Customer $customer, CollectionAssignmentService $assignments): JsonResponse
    {
        $tenant = $request->attributes->get('activeTenant');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $assignments->unassign($tenant, $request->user(), $customer, $data['reason'] ?? null);

        return response()->json([], 204);
    }
}

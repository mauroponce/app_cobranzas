<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionWorkflowService;
use App\Domain\Collections\PaymentService;
use App\Models\CollectionDeclaration;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionWorkflowApiController extends Controller
{
    public function start(Request $request, CollectionRoute $route, CollectionWorkflowService $workflow): JsonResponse
    {
        return response()->json(['data' => $workflow->startRoute($request->attributes->get('activeTenant'), $request->user(), $route)]);
    }

    public function declare(Request $request, CollectionWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'visit_id' => ['required', 'uuid'], 'operation_id' => ['required', 'uuid'], 'declared_at' => ['nullable', 'date'],
            'components' => ['required', 'array', 'min:1', 'max:2'], 'components.*.method' => ['required', 'in:cash,bank_transfer'],
            'components.*.amount' => ['required', 'string', 'max:32'], 'components.*.reference' => ['nullable', 'string', 'max:160'],
        ]);
        return response()->json(['data' => $workflow->declare($request->attributes->get('activeTenant'), $request->user(), $data)], 201);
    }

    public function noPayment(Request $request, CollectionVisit $visit, CollectionWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'in:absent,refused,insufficient_funds,other'], 'notes' => ['nullable', 'string', 'max:2000']]);
        return response()->json(['data' => $workflow->noPayment($request->attributes->get('activeTenant'), $request->user(), $visit, $data['reason'], $data['notes'] ?? null)]);
    }

    public function approve(Request $request, CollectionDeclaration $declaration, PaymentService $payments): JsonResponse
    {
        $data = $request->validate(['cash_received' => ['nullable', 'string', 'max:32']]);
        return response()->json(['data' => $payments->approveDeclaration($request->attributes->get('activeTenant'), $request->user(), $declaration, $data['cash_received'] ?? null)]);
    }
}

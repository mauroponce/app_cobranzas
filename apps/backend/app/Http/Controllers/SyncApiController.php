<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionPortfolioService;
use App\Domain\Collections\CollectionWorkflowService;
use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CashSettlement;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionIncident;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncApiController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json(['data' => ['server_time' => now()->toIso8601String(), 'authorization_version' => $request->attributes->get('activeTenant')->authorization_version]]);
    }

    public function push(Request $request, CollectionWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['device_id' => ['required', 'uuid'], 'operations' => ['required', 'array', 'max:50'], 'operations.*.operation_id' => ['required', 'uuid'], 'operations.*.schema_version' => ['required', 'integer', 'min:1'], 'operations.*.type' => ['required', 'string', 'in:collection_declaration,visit_outcome,cash_payment'], 'operations.*.payload' => ['required', 'array'], 'operations.*.recorded_at' => ['required', 'date']]);
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $device = DB::table('mobile_devices')->where('tenant_id', $tenant->id)->where('id', $data['device_id'])->where('membership_id', $membership->id)->first();
        abort_unless($device, 403);
        $results = [];
        foreach ($data['operations'] as $operation) {
            $results[] = $this->process($tenant, $request->user(), $device, $operation, $workflow);
        }

        return response()->json(['data' => ['operations' => $results]]);
    }

    public function pull(Request $request, CollectionPortfolioService $portfolio, AuthorizationService $authorization): JsonResponse
    {
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $data = $request->validate(['cursor' => ['nullable', 'integer', 'min:0'], 'portfolio_version' => ['nullable', 'integer', 'min:1']]);
        $reset = isset($data['portfolio_version']) && (int) $data['portfolio_version'] !== (int) $tenant->authorization_version;
        $cursor = $reset ? 0 : (int) ($data['cursor'] ?? 0);
        $canViewAssignments = $authorization->allows($membership, 'customers.view_assignments');
        $customers = $portfolio->customers($tenant, $membership)->with($canViewAssignments ? ['collectorAssignments' => fn ($query) => $query->whereNull('unassigned_at')->with('collectorMembership.user'), 'zoneAssignments' => fn ($query) => $query->whereNull('unassigned_at')->with('zone')] : [])->orderBy('customers.created_at')->offset($cursor)->limit(100)->get(['id', 'customer_number', 'first_name', 'last_name', 'status', 'updated_at'])->map(function (Customer $customer) use ($canViewAssignments): array {
            $data = $customer->only(['id', 'customer_number', 'first_name', 'last_name', 'status', 'updated_at']);
            if ($canViewAssignments) {
                $assignment = $customer->collectorAssignments->first();
                $zone = $customer->zoneAssignments->first()?->zone;
                $data['collector_name'] = $assignment?->collectorMembership?->user?->name;
                $data['zone_name'] = $zone?->name;
                $data['assignment_status'] = $assignment ? 'assigned' : 'unassigned';
            }

            return $data;
        });
        $ids = $customers->pluck('id');
        $entries = CustomerAccountEntry::forTenant($tenant)->whereIn('customer_id', $ids)->where('status', 'open')->orderBy('due_date')->get(['id', 'customer_id', 'sale_id', 'installment_id', 'entry_type', 'outstanding_amount', 'due_date']);
        $route = CollectionRoute::forTenant($tenant)
            ->where('collector_membership_id', $membership->id)
            ->where('route_date', today($tenant->timezone))
            ->whereIn('status', ['published', 'in_progress', 'submitted', 'completed'])
            ->orderByDesc('created_at')
            ->first();
        $routeStops = CollectionVisit::forTenant($tenant)
            ->when($route, fn ($query) => $query->where('route_id', $route->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->with('customer:id,first_name,last_name')
            ->orderBy('sequence')
            ->get(['id', 'customer_id', 'sale_id', 'sequence', 'status', 'outcome']);
        $routeCustomerIds = $routeStops->pluck('customer_id');
        $customerBalances = CustomerAccountEntry::forTenant($tenant)->whereIn('customer_id', $routeCustomerIds)->where('status', 'open')->selectRaw('customer_id, SUM(outstanding_amount) AS outstanding_amount')->groupBy('customer_id')->pluck('outstanding_amount', 'customer_id');
        $saleDueBalances = CustomerAccountEntry::forTenant($tenant)->whereIn('sale_id', $routeStops->pluck('sale_id'))->where('status', 'open')->whereDate('due_date', '<=', today($tenant->timezone))->selectRaw('sale_id, SUM(outstanding_amount) AS outstanding_amount')->groupBy('sale_id')->pluck('outstanding_amount', 'sale_id');
        $saleObligations = CustomerAccountEntry::forTenant($tenant)->whereIn('sale_id', $routeStops->pluck('sale_id'))->where('status', 'open')->where('outstanding_amount', '>', 0)->orderBy('due_date')->get(['id', 'sale_id'])->groupBy('sale_id');
        $stops = $routeStops->map(fn (CollectionVisit $stop): array => [
            'id' => $stop->id,
            'customer_id' => $stop->customer_id,
            'sale_id' => $stop->sale_id,
            'customer_name' => trim($stop->customer->last_name.', '.$stop->customer->first_name),
            'sequence' => $stop->sequence,
            'status' => $stop->status,
            'outcome' => $stop->outcome,
            'address' => '',
            'sale_due_amount_cents' => (string) Decimal::toInteger((string) ($saleDueBalances[$stop->sale_id] ?? '0.00')),
            'customer_total_outstanding_cents' => (string) Decimal::toInteger((string) ($customerBalances[$stop->customer_id] ?? '0.00')),
            'available_obligation_ids' => $saleObligations->get($stop->sale_id, collect())->pluck('id')->values()->all(),
        ]);

        $settlement = $route ? CashSettlement::forTenant($tenant)->where('route_id', $route->id)->first() : null;
        $pendingCash = $route ? CollectionDeclarationComponent::forTenant($tenant)->where('method', 'cash')->where('status', 'pending')
            ->whereHas('declaration.visit', fn ($query) => $query->where('route_id', $route->id))->sum('amount') : '0.00';
        $settlementData = $route ? [
            'id' => $settlement?->id,
            'status' => $settlement?->status ?? 'not_presented',
            'declared_cash_cents' => (string) Decimal::toInteger((string) ($settlement?->declared_amount ?? $pendingCash)),
            'received_cash_cents' => $settlement?->received_amount === null ? null : (string) Decimal::toInteger((string) $settlement->received_amount),
        ] : null;

        return response()->json(['data' => ['customers' => $customers, 'obligations' => $entries, 'route' => $route ? ['id' => $route->id, 'status' => $route->status, 'route_date' => $route->route_date->toDateString(), 'settlement' => $settlementData] : null, 'route_stops' => $stops, 'cursor' => $cursor + $customers->count(), 'has_more' => $customers->count() === 100, 'reset' => $reset, 'portfolio_version' => $tenant->authorization_version]]);
    }

    private function process($tenant, $actor, object $device, array $operation, CollectionWorkflowService $workflow): array
    {
        try {
            return DB::transaction(function () use ($tenant, $actor, $device, $operation, $workflow): array {
                $hash = $this->operationHash($operation);
                $existing = DB::table('mobile_sync_operations')->where('tenant_id', $tenant->id)->where('operation_id', $operation['operation_id'])->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $hash)) {
                        return ['operation_id' => $operation['operation_id'], 'status' => 'rejected', 'error' => 'El UUID fue reutilizado con otro contenido.'];
                    }

                    return ['operation_id' => $operation['operation_id'], ...json_decode($existing->result, true)];
                }
                $result = match ($operation['type']) {
                    'collection_declaration' => $this->declaration($tenant, $actor, $operation, $workflow, (bool) $device->revoked_at),
                    'visit_outcome' => $this->visitOutcome($tenant, $actor, $operation, $workflow, (bool) $device->revoked_at),
                    default => $this->rejectLegacyCash($tenant, $actor, $operation),
                };
                DB::table('mobile_sync_operations')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'device_id' => $device->id, 'operation_id' => $operation['operation_id'], 'type' => $operation['type'], 'payload_hash' => $hash, 'status' => $result['status'], 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'recorded_at' => $operation['recorded_at'], 'processed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

                return ['operation_id' => $operation['operation_id'], ...$result];
            });
        } catch (QueryException $exception) {
            $existing = DB::table('mobile_sync_operations')->where('tenant_id', $tenant->id)->where('operation_id', $operation['operation_id'])->first();
            if (! $existing) {
                throw $exception;
            }
            $hash = $this->operationHash($operation);

            return hash_equals($existing->payload_hash, $hash)
                ? ['operation_id' => $operation['operation_id'], ...json_decode($existing->result, true)]
                : ['operation_id' => $operation['operation_id'], 'status' => 'rejected', 'error' => 'El UUID fue reutilizado con otro contenido.'];
        }
    }

    private function declaration($tenant, $actor, array $operation, CollectionWorkflowService $workflow, bool $revoked): array
    {
        if ($revoked) {
            $this->recordSyncIncident($tenant, $actor, $operation, 'device_revoked');

            return ['status' => 'pending_review', 'reason' => 'device_revoked', 'error' => 'El dispositivo fue revocado; la declaración quedó preservada para revisión.'];
        }
        try {
            $declaration = $workflow->declare($tenant, $actor, $operation['payload'] + ['operation_id' => $operation['operation_id'], 'declared_at' => $operation['recorded_at']]);

            return ['status' => 'pending_review', 'declaration_id' => $declaration->id];
        } catch (ValidationException $exception) {
            $this->recordSyncIncident($tenant, $actor, $operation, 'financial_sync_conflict');

            return [
                'status' => 'pending_review',
                'error' => collect($exception->errors())->flatten()->first() ?? 'La declaración no cumple las condiciones de la visita.',
            ];
        } catch (\Throwable $error) {
            $this->recordSyncIncident($tenant, $actor, $operation, 'financial_sync_conflict');

            return ['status' => 'pending_review', 'error' => 'No se pudo registrar la declaración; quedó preservada para revisión.'];
        }
    }

    private function visitOutcome($tenant, $actor, array $operation, CollectionWorkflowService $workflow, bool $revoked): array
    {
        if ($revoked) {
            return ['status' => 'rejected', 'reason' => 'device_revoked', 'error' => 'El dispositivo fue revocado.'];
        }
        try {
            $payload = $operation['payload'];
            $visit = CollectionVisit::forTenant($tenant)->whereKey($payload['visit_id'] ?? null)->firstOrFail();
            $workflow->noPayment($tenant, $actor, $visit, (string) ($payload['reason'] ?? ''), $payload['notes'] ?? null);

            return ['status' => 'synced', 'visit_id' => $visit->id];
        } catch (\Throwable $error) {
            return ['status' => 'rejected', 'error' => $error instanceof ValidationException ? (collect($error->errors())->flatten()->first() ?? 'El resultado no es válido.') : 'No se pudo registrar el resultado.'];
        }
    }

    private function rejectLegacyCash($tenant, $actor, array $operation): array
    {
        $this->recordSyncIncident($tenant, $actor, $operation, 'legacy_cash_payment');

        return ['status' => 'pending_review', 'reason' => 'legacy_flow_disabled', 'error' => 'El cobro heredado no se confirmó; quedó preservado para revisión administrativa.'];
    }

    private function recordSyncIncident($tenant, $actor, array $operation, string $type): void
    {
        $visit = isset($operation['payload']['visit_id'])
            ? CollectionVisit::forTenant($tenant)->whereKey($operation['payload']['visit_id'])->first()
            : null;
        CollectionIncident::create([
            'tenant_id' => $tenant->id, 'route_id' => $visit?->route_id, 'visit_id' => $visit?->id,
            'type' => $type, 'status' => 'open',
            'details' => json_encode(['operation_id' => $operation['operation_id'], 'type' => $operation['type'], 'recorded_at' => $operation['recorded_at'], 'payload' => $operation['payload']], JSON_THROW_ON_ERROR),
            'reported_by' => $actor->id,
        ]);
    }

    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }

    private function operationHash(array $operation): string
    {
        return hash('sha256', json_encode($this->canonical([
            'schema_version' => $operation['schema_version'],
            'type' => $operation['type'],
            'payload' => $operation['payload'],
            'recorded_at' => $operation['recorded_at'],
        ]), JSON_THROW_ON_ERROR));
    }
}

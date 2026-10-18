<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Audit;
use App\Models\AuditEvent;
use App\Models\CollectionIncident;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class PilotSupportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('collections.reassign');
        $tenant = $request->attributes->get('activeTenant');

        $devices = MobileDevice::forTenant($tenant)
            ->with('membership.user')
            ->latest('last_seen_at')
            ->limit(50)
            ->get();
        $operations = DB::table('mobile_sync_operations as operations')
            ->join('mobile_devices as devices', function ($join): void {
                $join->on('devices.id', '=', 'operations.device_id')
                    ->on('devices.tenant_id', '=', 'operations.tenant_id');
            })
            ->join('tenant_memberships as memberships', function ($join): void {
                $join->on('memberships.id', '=', 'devices.membership_id')
                    ->on('memberships.tenant_id', '=', 'operations.tenant_id');
            })
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('operations.tenant_id', $tenant->id)
            ->select([
                'operations.operation_id',
                'operations.type',
                'operations.status',
                'operations.recorded_at',
                'operations.processed_at',
                'devices.id as device_id',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->latest('operations.created_at')
            ->limit(100)
            ->get();

        return view('support.index', [
            'devices' => $devices,
            'operations' => $operations,
            'incidents' => CollectionIncident::forTenant($tenant)->latest()->limit(50)->get(),
            'activity' => AuditEvent::query()->where('tenant_id', $tenant->id)->with('actor')->latest()->limit(50)->get(),
            'operationCounts' => DB::table('mobile_sync_operations')
                ->where('tenant_id', $tenant->id)
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function revokeDevice(Request $request, string $device): RedirectResponse
    {
        Gate::authorize('authorization.manage');
        $tenant = $request->attributes->get('activeTenant');
        $actor = $request->user();

        DB::transaction(function () use ($tenant, $actor, $device): void {
            $target = MobileDevice::forTenant($tenant)->lockForUpdate()->findOrFail($device);
            if ($target->revoked_at) {
                return;
            }

            $membership = $target->membership()->firstOrFail();
            if ($target->access_token_id) {
                PersonalAccessToken::query()
                    ->whereKey($target->access_token_id)
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $membership->user_id)
                    ->delete();
            }
            $target->update(['revoked_at' => now(), 'access_token_id' => null]);
            Audit::record('mobile_device.revoked', $target, $actor, $tenant->id, [
                'membership_id' => $membership->id,
            ]);
        });

        return back()->with('status', 'El dispositivo fue revocado y su sesión móvil quedó invalidada.');
    }
}

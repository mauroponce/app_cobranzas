<?php

namespace App\Http\Controllers;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDeviceApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['device_identifier' => ['required', 'string', 'min:16', 'max:128']]);
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $token = $request->user()->currentAccessToken();
        $device = MobileDevice::forTenant($tenant)
            ->where('membership_id', $membership->id)
            ->where('device_identifier', $data['device_identifier'])
            ->first();
        if ($device?->revoked_at) {
            $token?->delete();
            abort(403, 'El dispositivo fue revocado.');
        }
        $device ??= MobileDevice::create([
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'device_identifier' => $data['device_identifier'],
        ]);
        if ($device->access_token_id && $device->access_token_id !== $token?->getKey()) {
            PersonalAccessToken::query()
                ->whereKey($device->access_token_id)
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $membership->user_id)
                ->delete();
        }
        $device->update([
            'access_token_id' => $token?->getKey(),
            'last_seen_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $device->id]]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileDeviceApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['device_identifier' => ['required', 'string', 'min:16', 'max:128']]);
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $device = DB::table('mobile_devices')->where('tenant_id', $tenant->id)->where('membership_id', $membership->id)->where('device_identifier', $data['device_identifier'])->first();
        if (! $device) {
            $id = (string) Str::uuid();
            DB::table('mobile_devices')->insert(['id' => $id, 'tenant_id' => $tenant->id, 'membership_id' => $membership->id, 'device_identifier' => $data['device_identifier'], 'created_at' => now(), 'updated_at' => now()]);
            $device = (object) ['id' => $id, 'revoked_at' => null];
        }
        abort_if($device->revoked_at, 403);

        return response()->json(['data' => ['id' => $device->id]]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::selectOne('select 1')),
            'cache' => $this->check(function (): void {
                $key = 'health:readiness:'.bin2hex(random_bytes(8));
                Cache::put($key, 'ok', 10);

                if (Cache::pull($key) !== 'ok') {
                    throw new \RuntimeException('Cache round trip failed.');
                }
            }),
        ];
        $ready = ! in_array('failed', $checks, true);

        return response()->json([
            'data' => [
                'status' => $ready ? 'ready' : 'unavailable',
                'checks' => $checks,
            ],
        ], $ready ? 200 : 503);
    }

    private function check(callable $check): string
    {
        try {
            $check();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }
}

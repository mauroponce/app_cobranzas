<?php

namespace App\Domain\Commercial;

use Illuminate\Support\Facades\DB;

final class CommercialNumber
{
    public static function next(string $tenantId, string $kind, string $prefix): string
    {
        return DB::transaction(function () use ($tenantId, $kind, $prefix): string {
            DB::table('tenant_counters')->insertOrIgnore(['tenant_id' => $tenantId, 'kind' => $kind, 'next_number' => 1]);
            $row = DB::table('tenant_counters')->where('tenant_id', $tenantId)->where('kind', $kind)->lockForUpdate()->first();
            DB::table('tenant_counters')->where('tenant_id', $tenantId)->where('kind', $kind)->update(['next_number' => $row->next_number + 1]);

            return $prefix.str_pad((string) $row->next_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Commercial\CommercialNumber;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommercialNumberConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_requests_get_distinct_numbers_per_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Empresa concurrente', 'slug' => 'concurrente']);
        $path = tempnam(sys_get_temp_dir(), 'commercial-numbers-');
        DB::disconnect();
        $children = [];
        for ($worker = 0; $worker < 4; $worker++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge();
                try {
                    for ($i = 0; $i < 2; $i++) {
                        foreach (['customer' => 'CLI-', 'quotation' => 'PRE-'] as $kind => $prefix) {
                            $number = CommercialNumber::next($tenant->id, $kind, $prefix);
                            file_put_contents($path, $number."\n", FILE_APPEND | LOCK_EX);
                        }
                    }
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($path, 'ERROR:'.$e->getMessage()."\n", FILE_APPEND | LOCK_EX);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $numbers = array_filter(explode("\n", file_get_contents($path)));
        unlink($path);
        $this->assertCount(16, $numbers);
        $this->assertCount(16, array_unique($numbers));
        foreach (['customer', 'quotation'] as $kind) {
            $this->assertSame(9, (int) DB::table('tenant_counters')->where('tenant_id', $tenant->id)->where('kind', $kind)->value('next_number'));
        }
    }
}

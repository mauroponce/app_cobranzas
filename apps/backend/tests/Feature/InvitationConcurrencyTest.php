<?php

namespace Tests\Feature;

use App\Domain\Tenancy\InvitationService;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TenantInvitationMail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_simultaneous_acceptances_produce_one_membership(): void
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Concurrente', 'slug' => 'concurrente']);
        $owner = User::factory()->create();
        $existing = User::factory()->create(['email' => 'invited@example.test']);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $service = app(InvitationService::class);
        $invitation = $service->invite($tenant, $owner, $existing->email, 'viewer');
        $message = Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->first();
        $token = basename($message->toMail((object) [])->actionUrl);

        DB::beginTransaction();
        TenantInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
        $process = proc_open([PHP_BINARY, base_path('tests/Support/accept-invitation.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode(['token' => $token, 'user_id' => $existing->id], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        usleep(300000);
        $service->accept($token, $existing);
        DB::commit();
        $result = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertSame(0, $exit, $errors);

        $this->assertSame('410', $result);
        $this->assertSame(1, TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $existing->id)->count());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Tenancy\InvitationService;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TenantInvitationMail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
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

        $first = $this->startAcceptance($token, $existing->id);
        $second = $this->startAcceptance($token, $existing->id);

        $this->assertSame('ready', $this->readLine($first['pipes'][1]));
        $this->assertSame('ready', $this->readLine($second['pipes'][1]));

        foreach ([$first, $second] as $acceptance) {
            fwrite($acceptance['pipes'][0], "accept\n");
            fclose($acceptance['pipes'][0]);
        }

        $results = [$this->finishAcceptance($first), $this->finishAcceptance($second)];
        sort($results);

        $this->assertSame(['410', 'accepted'], $results);
        $this->assertSame(1, TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $existing->id)->count());
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame(1, AuditEvent::where('tenant_id', $tenant->id)->where('action', 'invitation.accepted')->count());
    }

    /**
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startAcceptance(string $token, string $userId): array
    {
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/accept-invitation.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode(['token' => $token, 'user_id' => $userId], JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>}  $acceptance
     */
    private function finishAcceptance(array $acceptance): string
    {
        $result = $this->readLine($acceptance['pipes'][1]);
        $errors = stream_get_contents($acceptance['pipes'][2]);
        fclose($acceptance['pipes'][1]);
        fclose($acceptance['pipes'][2]);

        $this->assertSame(0, proc_close($acceptance['process']), $errors);

        return $result;
    }

    /**
     * @param  resource  $stream
     */
    private function readLine($stream): string
    {
        $read = [$stream];
        $write = null;
        $except = null;
        $ready = stream_select($read, $write, $except, 10);
        $this->assertSame(1, $ready, 'El proceso de aceptación no respondió dentro de los 10 segundos.');

        return trim((string) fgets($stream));
    }
}

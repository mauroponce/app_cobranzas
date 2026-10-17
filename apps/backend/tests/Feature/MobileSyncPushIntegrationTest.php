<?php

namespace Tests\Feature;

use App\Domain\Tenancy\AuthorizationManagementService;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Permission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

class MobileSyncPushIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
    }

    private function member(string $role, ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 'empresa-'.uniqid()]);
        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $membership->fresh(['tenant']);
    }

    private function customer(TenantMembership $member): Customer
    {
        return Customer::create(['tenant_id' => $member->tenant_id, 'customer_number' => 'CLI-'.uniqid(), 'first_name' => 'Ana', 'last_name' => 'Offline', 'status' => 'active', 'created_by' => $member->user_id]);
    }

    private function entry(TenantMembership $member, Customer $customer, string $amount): CustomerAccountEntry
    {
        $sale = Sale::create(['tenant_id' => $member->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $member->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => $amount, 'currency' => 'ARS', 'subtotal' => $amount, 'discount_amount' => '0.00', 'down_payment_amount' => $amount, 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);

        return CustomerAccountEntry::create(['tenant_id' => $member->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => $amount, 'outstanding_amount' => $amount, 'due_date' => now()->toDateString(), 'status' => 'open']);
    }

    private function token(TenantMembership $member): string
    {
        auth()->forgetGuards();
        $token = $member->user->createToken('sync-test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $member->tenant_id])->assertOk();

        return $token;
    }

    private function device(TenantMembership $member, string $token, ?string $identifier = null): string
    {
        return $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => $identifier ?? 'device-'.str_replace('-', '', (string) Str::uuid())])->assertOk()->json('data.id');
    }

    private function operation(Customer $customer, string $amount = '10.00', ?string $id = null, ?CustomerAccountEntry $entry = null): array
    {
        return ['operation_id' => $id ?? (string) Str::uuid(), 'schema_version' => 1, 'type' => 'cash_payment', 'recorded_at' => now()->toIso8601String(), 'payload' => array_filter(['customer_id' => $customer->id, 'amount' => $amount, 'received_at' => now()->toIso8601String(), 'allocations' => $entry ? [['account_entry_id' => $entry->id]] : null])];
    }

    private function push(string $token, string $device, array ...$operations)
    {
        auth()->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => $operations]);
    }

    public function test_legacy_cash_push_is_preserved_but_never_confirmed_and_is_idempotent(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $entry = $this->entry($owner, $customer, '10.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $operation = $this->operation($customer, '10.00', null, $entry);
        $first = $this->push($token, $device, $operation)->assertOk()->json('data.operations.0');
        $retry = $this->push($token, $device, $operation)->assertOk()->json('data.operations.0');
        $this->assertSame('pending_review', $first['status']);
        $this->assertEquals($first, $retry);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('mobile_sync_operations', 1);
        $this->assertDatabaseCount('collection_incidents', 1);
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
    }

    public function test_same_uuid_with_different_payload_is_rejected_without_a_second_payment(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $this->entry($owner, $customer, '20.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $id = (string) Str::uuid();
        $this->push($token, $device, $this->operation($customer, '10.00', $id))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->push($token, $device, $this->operation($customer, '11.00', $id))->assertJsonPath('data.operations.0.status', 'rejected');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_partial_and_overpayment_never_change_balances_or_create_credit(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $entry = $this->entry($owner, $customer, '10.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $this->push($token, $device, $this->operation($customer, '4.00', null, $entry))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->push($token, $device, $this->operation($customer, '10.00'))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('customer_credits', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_repeated_legacy_payments_remain_under_review_without_allocation(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $entry = $this->entry($owner, $customer, '10.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $this->push($token, $device, $this->operation($customer, '10.00', null, $entry))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->push($token, $device, $this->operation($customer, '10.00', null, $entry))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertSame('0', (string) DB::table('payment_allocations')->sum('amount'));
    }

    public function test_unknown_device_and_other_tenant_device_are_forbidden(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $token = $this->token($owner);
        $this->push($token, (string) Str::uuid(), $this->operation($customer))->assertForbidden();
        $other = $this->member('owner');
        $otherToken = $this->token($other);
        $otherDevice = $this->device($other, $otherToken);
        $this->push($token, $otherDevice, $this->operation($customer))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_revoked_device_and_revoked_payment_permission_keep_the_received_operation_under_review(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $this->entry($owner, $customer, '20.00');
        $token = $this->token($collector);
        $device = $this->device($collector, $token);
        DB::table('mobile_devices')->where('id', $device)->update(['revoked_at' => now()]);
        $this->push($token, $device, $this->operation($customer))->assertJsonPath('data.operations.0.status', 'pending_review')->assertJsonPath('data.operations.0.reason', 'legacy_flow_disabled');
        $activeDevice = $this->device($collector, $token);
        app(AuthorizationManagementService::class)->setOverride($owner->tenant, $owner->user, $collector, Permission::where('key', 'payments.create')->firstOrFail(), 'deny', null);
        $this->push($token, $activeDevice, $this->operation($customer))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertDatabaseCount('collection_incidents', 2);
    }

    public function test_suspended_membership_and_disabled_feature_are_forbidden_before_financial_processing(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $this->entry($owner, $customer, '10.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $owner->update(['status' => 'suspended']);
        $this->push($token, $device, $this->operation($customer))->assertForbidden();
        $owner->update(['status' => 'active']);
        $feature = TenantFeature::where('tenant_id', $owner->tenant_id)->whereHas('feature', fn ($query) => $query->where('key', 'collections'))->firstOrFail();
        app(AuthorizationManagementService::class)->setFeature($owner->tenant, $owner->user, $feature, false);
        $this->push($token, $device, $this->operation($customer))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_own_payment_scope_is_denied_even_if_the_collector_was_the_seller(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $entry = $this->entry($owner, $customer, '10.00');
        Sale::create(['tenant_id' => $owner->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $collector->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => '1.00', 'currency' => 'ARS', 'subtotal' => '1.00', 'discount_amount' => '0.00', 'down_payment_amount' => '1.00', 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);
        app(AuthorizationManagementService::class)->setOverride($owner->tenant, $owner->user, $collector, Permission::where('key', 'payments.create')->firstOrFail(), 'allow', 'own');
        $token = $this->token($collector);
        $device = $this->device($collector, $token);
        $this->push($token, $device, $this->operation($customer, '10.00', null, $entry))->assertJsonPath('data.operations.0.status', 'pending_review');
        $foreign = $this->member('owner');
        $foreignCustomer = $this->customer($foreign);
        $this->push($token, $device, $this->operation($foreignCustomer))->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_batch_returns_independent_results_without_confirming_invalid_operations(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $entry = $this->entry($owner, $customer, '10.00');
        $token = $this->token($owner);
        $device = $this->device($owner, $token);
        $invalid = $this->operation($customer, '0.00');
        $valid = $this->operation($customer, '10.00', null, $entry);
        $response = $this->push($token, $device, $valid, $invalid)->assertOk();
        $response->assertJsonPath('data.operations.0.status', 'pending_review')->assertJsonPath('data.operations.1.status', 'pending_review');
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
    }

    public function test_postgresql_unique_operation_index_serializes_two_independent_connections(): void
    {
        $config = config('database.connections.pgsql');
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
        $first = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $second = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tenant = (string) Str::uuid();
        $user = (string) Str::uuid();
        $membership = (string) Str::uuid();
        $device = (string) Str::uuid();
        $operationId = (string) Str::uuid();
        $first->prepare("insert into users (id, name, email, password, created_at, updated_at) values (?, 'Concurrente', ?, 'secret', now(), now())")->execute([$user, 'concurrent-'.Str::uuid().'@test.local']);
        $first->prepare("insert into tenants (id, name, slug, created_at, updated_at) values (?, 'Concurrente', ?, now(), now())")->execute([$tenant, 'concurrent-'.Str::uuid()]);
        $first->prepare("insert into tenant_memberships (id, tenant_id, user_id, role, created_at, updated_at) values (?, ?, ?, 'owner', now(), now())")->execute([$membership, $tenant, $user]);
        $first->prepare("insert into mobile_devices (id, tenant_id, membership_id, device_identifier, created_at, updated_at) values (?, ?, ?, 'concurrent-device', now(), now())")->execute([$device, $tenant, $membership]);
        $first->beginTransaction();
        $statement = $first->prepare("insert into mobile_sync_operations (id, tenant_id, device_id, operation_id, type, payload_hash, status, result, recorded_at, processed_at, created_at, updated_at) values (?, ?, ?, ?, 'cash_payment', ?, 'applied', '{}', now(), now(), now(), now())");
        $statement->execute([(string) Str::uuid(), $tenant, $device, $operationId, str_repeat('a', 64)]);
        $second->exec("set lock_timeout = '100ms'");
        try {
            $duplicate = $second->prepare("insert into mobile_sync_operations (id, tenant_id, device_id, operation_id, type, payload_hash, status, result, recorded_at, processed_at, created_at, updated_at) values (?, ?, ?, ?, 'cash_payment', ?, 'applied', '{}', now(), now(), now(), now())");
            $duplicate->execute([(string) Str::uuid(), $tenant, $device, $operationId, str_repeat('a', 64)]);
            $this->fail('La segunda conexión no debe insertar el mismo UUID.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('lock timeout', $exception->getMessage());
        } finally {
            $first->commit();
        }
        try {
            $second->prepare("insert into mobile_sync_operations (id, tenant_id, device_id, operation_id, type, payload_hash, status, result, recorded_at, processed_at, created_at, updated_at) values (?, ?, ?, ?, 'cash_payment', ?, 'applied', '{}', now(), now(), now(), now())")->execute([(string) Str::uuid(), $tenant, $device, $operationId, str_repeat('a', 64)]);
            $this->fail('El índice único debe rechazar el duplicado tras liberar el bloqueo.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('duplicate key', $exception->getMessage());
        } finally {
            $first->exec("delete from mobile_sync_operations where tenant_id = '{$tenant}'");
            $first->exec("delete from mobile_devices where tenant_id = '{$tenant}'");
            $first->exec("delete from tenant_memberships where tenant_id = '{$tenant}'");
            $first->exec("delete from tenants where id = '{$tenant}'");
            $first->exec("delete from users where id = '{$user}'");
        }
    }
}

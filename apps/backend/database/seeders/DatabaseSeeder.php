<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Audit;
use App\Models\PlatformAdministrator;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $tenant = Tenant::firstOrCreate(['slug' => 'trotamundos'], [
            'name' => 'Trotamundos',
            'status' => 'active',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency' => 'ARS',
        ]);
        $password = config('demo.seed_password') ?: config('demo.admin_password');

        if (! is_string($password) || strlen($password) < 12) {
            $this->command?->warn('Definí DEV_SEED_PASSWORD o DEV_ADMIN_PASSWORD con al menos 12 caracteres para crear los usuarios de desarrollo.');

            return;
        }

        $accounts = [
            ['owner', config('demo.owner_email') ?: 'owner@trotamundos.local', 'Propietario de desarrollo'],
            ['admin', config('demo.admin_email') ?: 'admin@trotamundos.local', 'Administrador de desarrollo'],
            ['supervisor', 'supervisor@trotamundos.local', 'Supervisor de desarrollo'],
            ['seller', 'seller@trotamundos.local', 'Vendedor de desarrollo'],
            ['collector', 'collector@trotamundos.local', 'Cobrador de desarrollo'],
            ['logistics', 'logistics@trotamundos.local', 'Logística de desarrollo'],
            ['viewer', 'viewer@trotamundos.local', 'Consulta de desarrollo'],
        ];

        foreach ($accounts as [$role, $email, $name]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            TenantMembership::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['role' => $role, 'status' => 'active', 'joined_at' => now()],
            );
        }

        $platformUser = User::firstOrCreate(['email' => config('demo.platform_email') ?: 'platform@trotamundos.local'], [
            'name' => 'Operador de plataforma',
            'password' => $password,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $platformAdmin = PlatformAdministrator::firstOrCreate(['user_id' => $platformUser->id]);
        if ($platformAdmin->wasRecentlyCreated) {
            Audit::record('platform_admin.granted', $platformAdmin, null);
        }

        $this->call([AuthorizationCatalogSeeder::class, CommercialDemoSeeder::class]);
    }
}

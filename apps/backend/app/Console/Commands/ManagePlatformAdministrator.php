<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\Audit;
use App\Models\PlatformAdministrator;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePlatformAdministrator extends Command
{
    protected $signature = 'platform:administrator {email} {--revoke : Remove platform access}';

    protected $description = 'Grant or revoke audited platform administration access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || $user->status !== 'active') {
            $this->error('No existe una cuenta activa con ese correo.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user): void {
            if ($this->option('revoke')) {
                $administrator = PlatformAdministrator::where('user_id', $user->id)->lockForUpdate()->first();
                if ($administrator) {
                    Audit::record('platform_admin.revoked', $administrator, null, null, ['user_id' => $user->id]);
                    $administrator->delete();
                }
            } else {
                $administrator = PlatformAdministrator::firstOrCreate(['user_id' => $user->id]);
                if ($administrator->wasRecentlyCreated) {
                    Audit::record('platform_admin.granted', $administrator, null, null, ['user_id' => $user->id]);
                }
            }
        });
        $this->info($this->option('revoke') ? 'Acceso de plataforma revocado.' : 'Acceso de plataforma concedido.');

        return self::SUCCESS;
    }
}

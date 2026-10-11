<?php

namespace App\Domain\Tenancy;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class Audit
{
    public static function record(string $action, Model $subject, ?User $actor, ?string $tenantId = null, array $metadata = []): void
    {
        AuditEvent::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'metadata' => $metadata,
        ]);
    }
}

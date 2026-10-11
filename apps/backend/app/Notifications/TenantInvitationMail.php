<?php

namespace App\Notifications;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $invitationId,
        private readonly string $tenantName,
        private readonly string $acceptUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return TenantInvitation::whereKey($this->invitationId)
            ->where('tenant_id', $this->tenantId)
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invitación a {$this->tenantName} · App Cobranzas")
            ->greeting('Te invitaron a App Cobranzas')
            ->line("Podés unirte a {$this->tenantName} con este enlace. La invitación vence en siete días.")
            ->action('Aceptar invitación', $this->acceptUrl)
            ->line('Si no esperabas esta invitación, podés ignorar el correo.');
    }
}

<?php

namespace App\Notifications;

use App\Models\AMLScreening;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sprint 4 — sent to the compliance officer (admin team) when an AML screening
 * surfaces a sanctions / PEP / adverse-media match. The user is NOT notified
 * directly — that is handled out-of-band by the compliance team.
 */
class AMLFlaggedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AMLScreening $screening,
        public string $screenedUserName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = config('app.url');

        return (new MailMessage())
            ->subject("[Globalafrica+] AML Alert — {$this->screening->risk_level} risk")
            ->greeting('Bonjour,')
            ->line("Un screening AML pour **{$this->screenedUserName}** a remonté un signalement.")
            ->line('Détails :')
            ->line('• Sanctions : ' . ($this->screening->sanctions_match ? 'OUI' : 'non'))
            ->line('• PEP : '       . ($this->screening->pep_match ? 'OUI' : 'non'))
            ->line('• Médias défavorables : ' . ($this->screening->adverse_media_match ? 'OUI' : 'non'))
            ->line('• Risk level : ' . strtoupper($this->screening->risk_level))
            ->action('Ouvrir le dossier', "{$appUrl}/admin/users/{$this->screening->user_id}")
            ->line('Merci de revoir le dossier sous 24 h conformément à la procédure LCB-FT.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                 => 'aml_flagged',
            'screening_id'         => $this->screening->id,
            'user_id'              => $this->screening->user_id,
            'screened_name'        => $this->screenedUserName,
            'risk_level'           => $this->screening->risk_level,
            'sanctions_match'      => $this->screening->sanctions_match,
            'pep_match'            => $this->screening->pep_match,
            'adverse_media_match'  => $this->screening->adverse_media_match,
        ];
    }
}

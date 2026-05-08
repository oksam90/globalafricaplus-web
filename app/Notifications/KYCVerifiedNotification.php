<?php

namespace App\Notifications;

use App\Models\KYCVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sprint 4 — sent to the user when their KYC reaches `verified` or `certified`.
 * Channels: mail + database (persisted in the `notifications` table for the
 * dashboard's bell icon).
 */
class KYCVerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public KYCVerification $verification,
        public string $tierGranted,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = config('app.url');
        $label  = $this->tierGranted === 'certified' ? 'Certifié' : 'Vérifié';

        return (new MailMessage())
            ->subject("[Globalafrica+] Votre KYC est {$label} ✓")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre vérification d'identité a été approuvée — vous êtes désormais au niveau **{$label}**.")
            ->line('Vous pouvez maintenant accéder à toutes les fonctionnalités correspondantes : investissements, escrow, abonnements, mentorat.')
            ->action('Accéder à mon tableau de bord', "{$appUrl}/dashboard")
            ->line('Cette vérification est valide pendant 24 mois (Directive UEMOA N° 02/2015).');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'kyc_verified',
            'tier'              => $this->tierGranted,
            'verification_id'   => $this->verification->id,
            'verified_at'       => $this->verification->completed_at?->toIso8601String(),
            'expires_at'        => $this->verification->expires_at?->toIso8601String(),
        ];
    }
}

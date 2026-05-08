<?php

namespace App\Notifications;

use App\Models\KYCVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sprint 4 — informs the user that their KYC was rejected, with an actionable
 * next step (re-submit, or fall back to Document Verification when 0914).
 */
class KYCRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public KYCVerification $verification,
        public string $resultCode,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl  = config('app.url');
        $title   = $this->humanTitle();
        $message = $this->humanMessage();
        $cta     = $this->resultCode === '0914'
            ? ['label' => 'Essayer la vérification par document', 'url' => "{$appUrl}/kyc?step=2"]
            : ['label' => 'Réessayer ma vérification',           'url' => "{$appUrl}/kyc"];

        return (new MailMessage())
            ->subject("[Globalafrica+] {$title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($message)
            ->action($cta['label'], $cta['url'])
            ->line('Notre équipe support reste disponible si vous avez besoin d\'aide.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'kyc_rejected',
            'result_code'     => $this->resultCode,
            'verification_id' => $this->verification->id,
            'reason'          => $this->reason ?? $this->humanMessage(),
        ];
    }

    protected function humanTitle(): string
    {
        return match ($this->resultCode) {
            '0913' => 'Numéro d\'identité invalide',
            '0914' => 'Identité non trouvée',
            default => 'Vérification d\'identité rejetée',
        };
    }

    protected function humanMessage(): string
    {
        return match ($this->resultCode) {
            '0913' => 'Le numéro d\'identité saisi est invalide. Vérifiez son format puis ressaisissez-le.',
            '0914' => "Votre identité n\'a pas été trouvée auprès de l\'autorité gouvernementale. Vous pouvez essayer la vérification par document (CNI ou passeport).",
            default => $this->reason ?? 'Votre vérification d\'identité n\'a pas pu être validée. Vérifiez vos informations puis réessayez.',
        };
    }
}

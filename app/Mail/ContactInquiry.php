<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Inquiry submitted from the Enterprise "Nous contacter" form on /tarifs.
 *
 * Sent to contact@globalafricaplus.com. The sender's email is set as the
 * Reply-To, so a "Reply" in the inbox lands directly with the prospect.
 * The actual From address stays our system MAIL_FROM_ADDRESS to keep
 * SPF / DKIM / DMARC valid.
 */
class ContactInquiry extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $senderPhone,
        public string $subjectLine,
        public string $body,
        public ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  '[Contact Globalafrica+] ' . $this->subjectLine,
            replyTo:  [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-inquiry',
            with:     [
                'senderName'  => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'senderPhone' => $this->senderPhone,
                'subjectLine' => $this->subjectLine,
                'body'        => $this->body,
                'ip'          => $this->ip,
                'submittedAt' => now()->format('Y-m-d H:i:s T'),
            ],
        );
    }
}

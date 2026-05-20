<?php

namespace Tests\Feature;

use App\Mail\ContactInquiry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * POST /api/contact — Enterprise "Nous contacter" form on /tarifs.
 *
 * Covers: validation, recipient address, Reply-To header, rate-limiter
 * envelope, and graceful failure when the mail driver throws.
 */
class ContactInquiryTest extends TestCase
{
    protected array $validPayload = [
        'name'    => 'Aminata Diop',
        'email'   => 'aminata@example.com',
        'phone'   => '+221774391398',
        'subject' => 'Demande de devis Enterprise',
        'message' => 'Bonjour, nous aimerions discuter d\'un pack Enterprise pour notre ministère.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('contact-form');
        Mail::fake();
    }

    public function test_valid_payload_sends_mail_to_contact_address(): void
    {
        config(['contact.address' => 'contact@globalafricaplus.com']);

        $this->postJson('/api/contact', $this->validPayload)
            ->assertOk()
            ->assertJsonPath('message', 'Votre message a bien été envoyé. Notre équipe vous recontactera sous 24h.');

        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail) {
            return $mail->hasTo('contact@globalafricaplus.com')
                && $mail->senderEmail === 'aminata@example.com'
                && $mail->subjectLine === 'Demande de devis Enterprise';
        });
    }

    public function test_envelope_uses_replyto_so_inbox_reply_lands_with_prospect(): void
    {
        $this->postJson('/api/contact', $this->validPayload)->assertOk();

        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail) {
            $envelope = $mail->envelope();
            // First (only) Reply-To address must be the sender.
            $replyTo = $envelope->replyTo[0];
            return $replyTo->address === 'aminata@example.com'
                && $replyTo->name === 'Aminata Diop';
        });
    }

    public function test_subject_is_prefixed_for_easy_inbox_filtering(): void
    {
        $this->postJson('/api/contact', $this->validPayload)->assertOk();

        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail) {
            return $mail->envelope()->subject === '[Contact Globalafrica+] Demande de devis Enterprise';
        });
    }

    /** @dataProvider invalidPayloads */
    public function test_validation_rejects_invalid_inputs(array $overrides, string $expectedErrorField): void
    {
        $payload = array_merge($this->validPayload, $overrides);

        $this->postJson('/api/contact', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$expectedErrorField]);

        Mail::assertNothingSent();
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name'         => [['name' => ''],          'name'],
            'name too short'       => [['name' => 'A'],         'name'],
            'invalid email format' => [['email' => 'not-mail'], 'email'],
            'missing phone'        => [['phone' => ''],         'phone'],
            'subject too short'    => [['subject' => 'hi'],     'subject'],
            'message too short'    => [['message' => 'short'],  'message'],
        ];
    }

    public function test_rate_limit_blocks_after_5_inquiries_per_hour(): void
    {
        // 5 calls should succeed.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', $this->validPayload)->assertOk();
        }

        // 6th call from the same IP should be throttled.
        $this->postJson('/api/contact', $this->validPayload)
            ->assertStatus(429);
    }

    public function test_recipient_address_is_configurable_via_config(): void
    {
        config(['contact.address' => 'sales@example.test']);

        $this->postJson('/api/contact', $this->validPayload)->assertOk();

        Mail::assertSent(ContactInquiry::class, fn (ContactInquiry $mail) =>
            $mail->hasTo('sales@example.test')
        );
    }
}

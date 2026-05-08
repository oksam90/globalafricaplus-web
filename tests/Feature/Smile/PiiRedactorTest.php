<?php

namespace Tests\Feature\Smile;

use App\Support\PiiRedactor;
use Tests\TestCase;

/**
 * Audit fix 2026-05 (§ 4.2.4) — redactor must strip PII from anything that
 * lands in payment_logs.payload / kyc_verifications.callback_payload while
 * preserving forensic-useful fields.
 */
class PiiRedactorTest extends TestCase
{
    // ── primitive helpers ────────────────────────────────────────────────

    public function test_redact_email_keeps_first_char_and_domain(): void
    {
        $this->assertSame('a***@africaplus.test', PiiRedactor::redactEmail('aminata@africaplus.test'));
        $this->assertSame('j***@example.com',     PiiRedactor::redactEmail('john.doe@example.com'));
    }

    public function test_redact_email_handles_null_and_empty(): void
    {
        $this->assertNull(PiiRedactor::redactEmail(null));
        $this->assertNull(PiiRedactor::redactEmail(''));
        $this->assertSame('***', PiiRedactor::redactEmail('not-an-email'));
    }

    public function test_redact_phone_keeps_last_4_with_country_prefix(): void
    {
        // 12 digits: +221 + 5 masked + last 4 visible
        $this->assertSame('+221*****1398', PiiRedactor::redactPhone('+221774391398'));
        // strips spaces, 12 digits → +336 + 5 masked + last 4
        $this->assertSame('+336*****6789', PiiRedactor::redactPhone('+33 6 12 34 56 789'));
    }

    public function test_redact_phone_short_and_null_inputs(): void
    {
        $this->assertNull(PiiRedactor::redactPhone(null));
        $this->assertSame('****', PiiRedactor::redactPhone('1234'));
        $this->assertSame('*', PiiRedactor::redactPhone('5'));
    }

    public function test_initials_keeps_first_letter_per_word(): void
    {
        $this->assertSame('A. D.', PiiRedactor::initials('Aminata Diop'));
        $this->assertSame('J. P. D.', PiiRedactor::initials('Jean Pierre Dupont'));
        $this->assertNull(PiiRedactor::initials(null));
        $this->assertNull(PiiRedactor::initials(''));
    }

    // ── PayDunya redaction ───────────────────────────────────────────────

    public function test_redact_paydunya_webhook_keeps_invoice_data_strips_customer(): void
    {
        $payload = [
            'data' => [
                'mode'   => 'live',
                'status' => 'completed',
                'invoice' => [
                    'token'        => 'tok_abc123',
                    'total_amount' => '5000',
                    'description'  => 'Investment',
                    'invoice_url'  => 'https://paydunya.com/sandbox-checkout/xyz',  // signed
                    'receipt_url'  => 'https://paydunya.com/receipt/xyz.pdf',       // signed
                ],
                'custom_data' => [
                    'transaction_id' => 42,
                    'user_id'        => 7,
                    'phone'          => '+221774391398', // smuggled PII
                    'email'          => 'leak@example.com',
                ],
                'customer' => [
                    'name'    => 'Aminata Diop',
                    'email'   => 'aminata@africaplus.test',
                    'phone'   => '+221774391398',
                    'country' => 'SN',
                ],
            ],
        ];

        $clean = PiiRedactor::redactPaydunyaWebhook($payload);

        // forensic-useful kept
        $this->assertSame('tok_abc123', $clean['invoice']['token']);
        $this->assertSame('5000',       $clean['invoice']['total_amount']);
        $this->assertSame('completed',  $clean['status']);

        // signed URLs gone
        $this->assertArrayNotHasKey('invoice_url', $clean['invoice']);
        $this->assertArrayNotHasKey('receipt_url', $clean['invoice']);

        // customer redacted
        $this->assertSame('A. D.',                    $clean['customer']['name']);
        $this->assertSame('a***@africaplus.test',     $clean['customer']['email']);
        $this->assertSame('+221*****1398',            $clean['customer']['phone']);
        $this->assertSame('SN',                       $clean['customer']['country']);

        // custom_data smuggled PII removed, references kept
        $this->assertSame(42, $clean['custom_data']['transaction_id']);
        $this->assertSame(7,  $clean['custom_data']['user_id']);
        $this->assertArrayNotHasKey('phone', $clean['custom_data']);
        $this->assertArrayNotHasKey('email', $clean['custom_data']);
    }

    public function test_redact_paydunya_status_strips_inner_customer_and_signed_urls(): void
    {
        $raw = [
            'status'      => 'completed',
            'token'       => 'tok_abc',
            'invoice_url' => 'https://signed/...',
            'receipt_url' => 'https://signed/receipt.pdf',
            'customer'    => [
                'name'  => 'Tamane Eric',
                'email' => 'tamane@example.com',
                'phone' => '+225011223344',
            ],
        ];

        $clean = PiiRedactor::redactPaydunyaStatus($raw);
        $this->assertArrayNotHasKey('invoice_url', $clean);
        $this->assertArrayNotHasKey('receipt_url', $clean);
        $this->assertSame('T. E.',                $clean['customer']['name']);
        $this->assertSame('t***@example.com',     $clean['customer']['email']);
        $this->assertSame('+225*****3344',        $clean['customer']['phone']);
    }

    // ── Smile callback redaction ─────────────────────────────────────────

    public function test_redact_smile_callback_drops_image_links_and_receipt(): void
    {
        $payload = [
            'SmileJobID'       => '0000020855',
            'ResultCode'       => '0810',
            'ResultText'       => 'Approved',
            'ConfidenceValue'  => '99',
            'PartnerParams'    => ['user_id' => '1', 'job_id' => 'j', 'job_type' => 1],
            'Actions'          => ['Selfie_Check' => 'Passed'],
            'ImageLinks'       => [
                'selfie_image'  => 'https://signed-s3-url-with-real-selfie.png',
                'id_photo_image' => 'https://signed-s3-url-with-id.jpg',
            ],
            'KYCReceipt'       => 'https://signed-s3-url-receipt.pdf',
            'signature'        => 'base64sig',
            'timestamp'        => '2026-05-08T10:00:00Z',
        ];

        $clean = PiiRedactor::redactSmileCallback($payload);

        // forensic state preserved
        $this->assertSame('0810',     $clean['ResultCode']);
        $this->assertSame('Approved', $clean['ResultText']);
        $this->assertArrayHasKey('Actions', $clean);
        $this->assertArrayHasKey('PartnerParams', $clean);

        // sensitive bits gone
        $this->assertArrayNotHasKey('ImageLinks', $clean);
        $this->assertArrayNotHasKey('KYCReceipt', $clean);
        $this->assertArrayNotHasKey('signature',  $clean);
    }

    // ── SAR (CENTIF mock) redaction ──────────────────────────────────────

    public function test_redact_sar_payload_masks_user_name_and_email(): void
    {
        $clean = PiiRedactor::redactSarPayload([
            'user_id'             => 42,
            'user_name'           => 'Aminata Diop',
            'user_email'          => 'aminata@africaplus.test',
            'screening_id'        => 7,
            'risk_level'          => 'critical',
            'sanctions_match'     => true,
            'pep_match'           => false,
            'adverse_media_match' => false,
            'mode'                => 'mock',
        ]);

        // user_id is the cross-reference key — must stay intact.
        $this->assertSame(42, $clean['user_id']);

        // PII redacted
        $this->assertSame('A. D.',                $clean['user_name']);
        $this->assertSame('a***@africaplus.test', $clean['user_email']);

        // compliance fields kept
        $this->assertSame('critical', $clean['risk_level']);
        $this->assertTrue($clean['sanctions_match']);
        $this->assertSame('mock',     $clean['mode']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * POST /api/contact — public endpoint backing the Enterprise "Nous contacter"
 * form on /tarifs. Sends a markdown mail to contact@globalafricaplus.com
 * with the prospect's name, email, phone, subject and message; the sender's
 * email is set as Reply-To so a "Reply" lands with them directly.
 *
 * Protected by the `contact-form` rate limiter (5/hour/IP) — see
 * AppServiceProvider::boot().
 */
class ContactController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:100'],
            'email'   => ['required', 'email:rfc', 'max:150'],
            'phone'   => ['required', 'string', 'min:6', 'max:30'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $recipient = (string) config('contact.address', 'contact@globalafricaplus.com');

        try {
            Mail::to($recipient)->send(new ContactInquiry(
                senderName:  $data['name'],
                senderEmail: $data['email'],
                senderPhone: $data['phone'],
                subjectLine: $data['subject'],
                body:        $data['message'],
                ip:          $request->ip(),
            ));
        } catch (\Throwable $e) {
            // Mail driver failure shouldn't expose internals to the visitor —
            // log on our side and surface a clean message.
            Log::error('contact.inquiry_send_failed', [
                'error'   => $e->getMessage(),
                'email'   => $data['email'],
                'subject' => $data['subject'],
                'ip'      => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Une erreur technique est survenue. Merci de réessayer dans quelques minutes ou de nous écrire directement à ' . $recipient,
            ], 502);
        }

        Log::info('contact.inquiry_sent', [
            'from'    => $data['email'],
            'subject' => $data['subject'],
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Votre message a bien été envoyé. Notre équipe vous recontactera sous 24h.',
        ]);
    }
}

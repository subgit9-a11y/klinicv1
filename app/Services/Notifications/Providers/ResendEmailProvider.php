<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email provider using Resend API or fallback to Laravel SMTP.
 *
 * When Resend is configured, sends via the Resend HTTP API. Otherwise
 * falls back to the default Laravel mail driver (SMTP). This ensures
 * email delivery works even without a Resend key.
 */
class ResendEmailProvider implements EmailProviderInterface
{
    private readonly string $resendKey;

    private readonly string $fromAddress;

    public function __construct()
    {
        $this->resendKey = (string) config('services.resend.key', '');
        $this->fromAddress = (string) config('mail.from.address', 'noreply@klinic360.com');
    }

    public function name(): string
    {
        return 'RESEND';
    }

    public function isConfigured(): bool
    {
        // Resend key OR a working SMTP config.
        return $this->resendKey !== '' || config('mail.default') !== null;
    }

    public function send(string $to, string $subject, string $htmlBody, array $data = []): array
    {
        // Use Resend API if configured.
        if ($this->resendKey !== '') {
            return $this->sendViaResend($to, $subject, $htmlBody);
        }

        // Fallback to Laravel mail driver (SMTP).
        return $this->sendViaSmtp($to, $subject, $htmlBody);
    }

    /**
     * @return array{success: bool, reference: ?string, message: string}
     */
    private function sendViaResend(string $to, string $subject, string $htmlBody): array
    {
        try {
            $response = Http::withToken($this->resendKey)
                ->timeout(30)
                ->post('https://api.resend.com/emails', [
                    'from' => $this->fromAddress,
                    'to' => [$to],
                    'subject' => $subject,
                    'html' => $htmlBody,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return ['success' => true, 'reference' => $data['id'] ?? null, 'message' => 'Email sent via Resend'];
            }

            Log::warning('Resend send failed', ['status' => $response->status()]);

            return ['success' => false, 'reference' => null, 'message' => 'Resend error: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('Resend send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'reference' => null, 'message' => 'API exception'];
        }
    }

    /**
     * @return array{success: bool, reference: ?string, message: string}
     */
    private function sendViaSmtp(string $to, string $subject, string $htmlBody): array
    {
        try {
            Mail::html($htmlBody, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return ['success' => true, 'reference' => Str::uuid()->toString(), 'message' => 'Email sent via SMTP'];
        } catch (\Throwable $e) {
            Log::error('SMTP send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'reference' => null, 'message' => 'SMTP send failed'];
        }
    }
}

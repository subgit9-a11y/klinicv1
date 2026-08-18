<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MSG91 SMS provider.
 *
 * Sends transactional SMS via the MSG91 API. Real calls are made only
 * when configured; otherwise gracefully reports "not configured".
 */
class Msg91SmsProvider implements SmsProviderInterface
{
    private readonly string $authKey;

    private readonly string $senderId;

    private readonly string $route;

    public function __construct()
    {
        $this->authKey = (string) config('services.msg91.auth_key', '');
        $this->senderId = (string) config('services.msg91.sender_id', 'KLINIC');
        $this->route = (string) config('services.msg91.route', '4');
    }

    public function name(): string
    {
        return 'MSG91';
    }

    public function isConfigured(): bool
    {
        return $this->authKey !== '';
    }

    public function send(string $to, string $message, array $variables = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'reference' => null, 'message' => 'MSG91 not configured'];
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->withHeaders(['authkey' => $this->authKey])
                ->post('https://api.msg91.com/api/v2/sendsms', [
                    'sender' => $this->senderId,
                    'route' => $this->route,
                    'country' => '91',
                    'unicode' => '0',
                    'sms' => [
                        [
                            'message' => $message,
                            'to' => [$to],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $reference = $data['message'] ?? null;

                return ['success' => true, 'reference' => $reference, 'message' => 'SMS sent'];
            }

            Log::warning('MSG91 send failed', ['status' => $response->status()]);

            return ['success' => false, 'reference' => null, 'message' => 'API error: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('MSG91 send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'reference' => null, 'message' => 'API exception'];
        }
    }
}

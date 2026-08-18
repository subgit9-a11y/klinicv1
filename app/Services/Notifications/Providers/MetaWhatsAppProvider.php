<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API provider.
 *
 * Sends template messages via the Meta Graph API. Real calls are made
 * only when configured; otherwise gracefully reports "not configured".
 */
class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    private readonly string $apiToken;

    private readonly string $phoneNumberId;

    private readonly string $apiVersion;

    public function __construct()
    {
        $this->apiToken = (string) config('services.whatsapp.api_token', '');
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $this->apiVersion = (string) config('services.whatsapp.api_version', 'v18.0');
    }

    public function name(): string
    {
        return 'META_WHATSAPP';
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '' && $this->phoneNumberId !== '';
    }

    public function send(string $to, string $templateName, array $variables = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'reference' => null, 'message' => 'WhatsApp not configured'];
        }

        $components = $this->buildComponents($variables);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'en'],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? null;

                return ['success' => true, 'reference' => $messageId, 'message' => 'WhatsApp sent'];
            }

            Log::warning('WhatsApp send failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'reference' => null, 'message' => 'API error: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'reference' => null, 'message' => 'API exception'];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildComponents(array $variables): array
    {
        $params = [];
        foreach (array_values($variables) as $value) {
            $params[] = ['type' => 'text', 'text' => (string) $value];
        }

        if (empty($params)) {
            return [];
        }

        return [
            [
                'type' => 'body',
                'parameters' => $params,
            ],
        ];
    }
}

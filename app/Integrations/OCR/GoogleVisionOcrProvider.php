<?php

declare(strict_types=1);

namespace App\Integrations\OCR;

use App\Contracts\OCRProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Google Cloud Vision API OCR provider.
 *
 * Performs text detection (TEXT_DETECTION) on document/lab-report images.
 * Real API calls are made only when an API key is configured; otherwise the
 * provider reports "not configured" and callers degrade gracefully (no faked
 * success).
 */
class GoogleVisionOcrProvider implements OCRProviderInterface
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_vision.api_key', '');
        $this->baseUrl = (string) config(
            'services.google_vision.base_url',
            'https://vision.googleapis.com/v1'
        );
    }

    public function name(): string
    {
        return 'GOOGLE_VISION';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Extract text from an image file via Google Vision.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function extract(string $disk, string $path): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'text' => '', 'message' => 'Google Vision OCR provider not configured'];
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return ['success' => false, 'text' => '', 'message' => 'File not found'];
            }

            $content = Storage::disk($disk)->get($path);
            $base64 = base64_encode((string) $content);

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/images:annotate?key={$this->apiKey}", [
                    'requests' => [[
                        'image' => ['content' => $base64],
                        'features' => [['type' => 'TEXT_DETECTION']],
                    ]],
                ]);

            if (! $response->successful()) {
                Log::error('Google Vision OCR failed', ['status' => $response->status()]);

                return ['success' => false, 'text' => '', 'message' => 'OCR API error: HTTP '.$response->status()];
            }

            $text = $this->extractFullText($response->json());

            return ['success' => true, 'text' => $text, 'message' => 'ok'];
        } catch (\Throwable $e) {
            Log::error('Google Vision OCR exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'text' => '', 'message' => $e->getMessage()];
        }
    }

    /**
     * Concatenate all text annotations (full + per-block) from the Vision response.
     */
    private function extractFullText(?array $json): string
    {
        $responses = $json['responses'] ?? [];
        if (empty($responses)) {
            return '';
        }

        // The first annotation holds the full document text; fall back to
        // concatenating per-word descriptions if absent.
        $first = $responses[0]['textAnnotations'][0]['description'] ?? null;
        if (is_string($first)) {
            return trim($first);
        }

        $parts = [];
        foreach (($responses[0]['textAnnotations'] ?? []) as $annotation) {
            if (isset($annotation['description'])) {
                $parts[] = $annotation['description'];
            }
        }

        return trim(implode(' ', $parts));
    }
}

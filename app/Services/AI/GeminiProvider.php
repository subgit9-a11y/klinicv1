<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini AI provider.
 *
 * Calls the Gemini REST API (generateContent) for text completion,
 * structured JSON output, and media analysis. Real API calls are made
 * only when an API key is configured; otherwise gracefully reports
 * "not configured".
 *
 * All responses are returned as raw AI output — the caller (AIManager)
 * is responsible for storing them as DRAFT for human review.
 */
class GeminiProvider implements AIProviderInterface
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $defaultModel;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->baseUrl = (string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->defaultModel = (string) config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function name(): string
    {
        return 'GEMINI';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{content: string, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function complete(string $prompt, array $context = [], ?string $model = null): array
    {
        if (!$this->isConfigured()) {
            return ['content' => '', 'usage' => [], 'model' => $model ?? $this->defaultModel, 'duration_ms' => 0];
        }

        $model = $model ?? $this->defaultModel;
        $systemInstruction = $context['system'] ?? null;

        $start = microtime(true);

        try {
            $payload = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => $context['temperature'] ?? 0.3,
                    'maxOutputTokens' => $context['max_tokens'] ?? 2048,
                ],
            ];

            if ($systemInstruction !== null) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => $systemInstruction]],
                ];
            }

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", $payload);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $usage = $data['usageMetadata'] ?? [];

                return [
                    'content' => $content,
                    'usage' => $usage,
                    'model' => $model,
                    'duration_ms' => $durationMs,
                ];
            }

            Log::warning('Gemini complete failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['content' => '', 'usage' => [], 'model' => $model, 'duration_ms' => $durationMs];
        } catch (\Throwable $e) {
            Log::error('Gemini complete exception', ['error' => $e->getMessage()]);

            return ['content' => '', 'usage' => [], 'model' => $model, 'duration_ms' => 0];
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{data: array<string, mixed>, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function structured(string $prompt, array $context = [], ?string $model = null): array
    {
        if (!$this->isConfigured()) {
            return ['data' => [], 'usage' => [], 'model' => $model ?? $this->defaultModel, 'duration_ms' => 0];
        }

        $model = $model ?? $this->defaultModel;
        $start = microtime(true);

        try {
            $payload = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => $context['temperature'] ?? 0.2,
                    'maxOutputTokens' => $context['max_tokens'] ?? 2048,
                    'responseMimeType' => 'application/json',
                ],
            ];

            if (isset($context['system'])) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => $context['system']]],
                ];
            }

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", $payload);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $decoded = json_decode($text, true) ?? [];
                $usage = $data['usageMetadata'] ?? [];

                return [
                    'data' => $decoded,
                    'usage' => $usage,
                    'model' => $model,
                    'duration_ms' => $durationMs,
                ];
            }

            Log::warning('Gemini structured failed', ['status' => $response->status()]);

            return ['data' => [], 'usage' => [], 'model' => $model, 'duration_ms' => $durationMs];
        } catch (\Throwable $e) {
            Log::error('Gemini structured exception', ['error' => $e->getMessage()]);

            return ['data' => [], 'usage' => [], 'model' => $model, 'duration_ms' => 0];
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{content: string, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function analyseMedia(string $disk, string $path, string $prompt, array $context = [], ?string $model = null): array
    {
        if (!$this->isConfigured()) {
            return ['content' => '', 'usage' => [], 'model' => $model ?? $this->defaultModel, 'duration_ms' => 0];
        }

        $model = $model ?? $this->defaultModel;
        $start = microtime(true);

        try {
            // Read the file from disk and base64-encode it for inline_data.
            $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($path);
            if ($content === null) {
                return ['content' => '', 'usage' => [], 'model' => $model, 'duration_ms' => 0];
            }

            $mimeType = \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($path);
            $base64 = base64_encode($content);

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $context['temperature'] ?? 0.3,
                    'maxOutputTokens' => $context['max_tokens'] ?? 2048,
                ],
            ];

            $response = Http::timeout(90)
                ->post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", $payload);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                return [
                    'content' => $text,
                    'usage' => $data['usageMetadata'] ?? [],
                    'model' => $model,
                    'duration_ms' => $durationMs,
                ];
            }

            Log::warning('Gemini analyseMedia failed', ['status' => $response->status()]);

            return ['content' => '', 'usage' => [], 'model' => $model, 'duration_ms' => $durationMs];
        } catch (\Throwable $e) {
            Log::error('Gemini analyseMedia exception', ['error' => $e->getMessage()]);

            return ['content' => '', 'usage' => [], 'model' => $model, 'duration_ms' => 0];
        }
    }
}

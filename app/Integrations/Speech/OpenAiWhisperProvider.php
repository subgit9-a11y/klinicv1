<?php

declare(strict_types=1);

namespace App\Integrations\Speech;

use App\Contracts\SpeechProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * OpenAI Whisper API speech-to-text provider.
 *
 * Transcribes audio files via the OpenAI /audio/transcriptions endpoint.
 * Real API calls are made only when an API key is configured; otherwise the
 * provider reports "not configured" and callers degrade gracefully.
 */
class OpenAiWhisperProvider implements SpeechProviderInterface
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.api_key', '');
        $this->baseUrl = (string) config('services.openai.base_url', 'https://api.openai.com/v1');
        $this->model = (string) config('services.openai.whisper_model', 'whisper-1');
    }

    public function name(): string
    {
        return 'OPENAI_WHISPER';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Transcribe an audio file via OpenAI Whisper.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function transcribe(string $disk, string $path): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'text' => '', 'message' => 'OpenAI Whisper provider not configured'];
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return ['success' => false, 'text' => '', 'message' => 'File not found'];
            }

            $content = Storage::disk($disk)->get($path);
            $filename = basename($path);
            $mimeType = $this->mimeType($path);

            $response = Http::withToken($this->apiKey)
                ->attach('file', (string) $content, $filename, ['Content-Type' => $mimeType])
                ->post("{$this->baseUrl}/audio/transcriptions", [
                    'model' => $this->model,
                    'response_format' => 'text',
                ]);

            if (! $response->successful()) {
                Log::error('OpenAI Whisper failed', ['status' => $response->status()]);

                return ['success' => false, 'text' => '', 'message' => 'Transcription API error: HTTP '.$response->status()];
            }

            return ['success' => true, 'text' => trim($response->body()), 'message' => 'ok'];
        } catch (\Throwable $e) {
            Log::error('OpenAI Whisper exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'text' => '', 'message' => $e->getMessage()];
        }
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
            default => 'application/octet-stream',
        };
    }
}

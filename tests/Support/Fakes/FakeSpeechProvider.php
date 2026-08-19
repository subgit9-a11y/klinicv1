<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Contracts\SpeechProviderInterface;

/**
 * Configured fake speech-to-text provider for tests — avoids real HTTP.
 */
class FakeSpeechProvider implements SpeechProviderInterface
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'FAKE_SPEECH';
    }

    public function transcribe(string $disk, string $path): array
    {
        return ['success' => true, 'text' => 'Patient reports joint pain for three days.', 'message' => 'ok'];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Contracts\OCRProviderInterface;

/**
 * Configured fake OCR provider for tests — reports isConfigured() true and
 * returns canned text, simulating a working integration without real HTTP.
 */
class FakeOcrProvider implements OCRProviderInterface
{
    public bool $called = false;

    public function isConfigured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'FAKE_OCR';
    }

    public function extract(string $disk, string $path): array
    {
        $this->called = true;

        return ['success' => true, 'text' => 'Haemoglobin: 14.2 g/dL', 'message' => 'ok'];
    }
}

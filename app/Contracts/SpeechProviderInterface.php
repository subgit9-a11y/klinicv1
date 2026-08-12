<?php

declare(strict_types=1);

namespace App\Contracts;

interface SpeechProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Transcribe an audio file to text.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function transcribe(string $disk, string $path): array;
}

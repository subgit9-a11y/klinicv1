<?php

declare(strict_types=1);

namespace App\Contracts;

interface AIProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Send a text prompt and return a text response.
     *
     * @param  array<string, mixed>  $context
     * @return array{content: string, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function complete(string $prompt, array $context = [], ?string $model = null): array;

    /**
     * Send a prompt expecting structured (JSON) output.
     *
     * @param  array<string, mixed>  $context
     * @return array{data: array<string, mixed>, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function structured(string $prompt, array $context = [], ?string $model = null): array;

    /**
     * Analyse a document or image stored on a disk.
     *
     * @param  array<string, mixed>  $context
     * @return array{content: string, usage: array<string, mixed>, model: string, duration_ms: int}
     */
    public function analyseMedia(string $disk, string $path, string $prompt, array $context = [], ?string $model = null): array;
}

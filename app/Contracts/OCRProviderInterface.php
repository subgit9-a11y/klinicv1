<?php

declare(strict_types=1);

namespace App\Contracts;

interface OCRProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Extract text from a document/image.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function extract(string $disk, string $path): array;
}

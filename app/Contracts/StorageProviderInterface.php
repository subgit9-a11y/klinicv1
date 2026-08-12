<?php

declare(strict_types=1);

namespace App\Contracts;

interface StorageProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Store a file privately. Returns the stored path.
     */
    public function store(string $content, string $path, ?string $mimeType = null): string;

    /**
     * Generate a temporary signed URL for private access.
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiry): string;

    /**
     * Stream a private file for authorized access.
     *
     * @return resource
     */
    public function stream(string $path);

    public function delete(string $path): bool;

    public function exists(string $path): bool;
}

<?php

declare(strict_types=1);

namespace App\Contracts;

interface EmailProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Send an email. Returns a delivery reference.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, reference: ?string, message: string}
     */
    public function send(string $to, string $subject, string $htmlBody, array $data = []): array;
}

<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Send an SMS. Returns a delivery reference.
     *
     * @param  array<string, mixed>  $variables
     * @return array{success: bool, reference: ?string, message: string}
     */
    public function send(string $to, string $message, array $variables = []): array;
}

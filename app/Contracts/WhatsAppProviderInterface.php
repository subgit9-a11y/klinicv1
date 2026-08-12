<?php

declare(strict_types=1);

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Send a WhatsApp message. Returns a delivery reference.
     *
     * @param  array<string, mixed>  $variables
     * @return array{success: bool, reference: ?string, message: string}
     */
    public function send(string $to, string $templateName, array $variables = []): array;
}

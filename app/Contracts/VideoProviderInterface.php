<?php

declare(strict_types=1);

namespace App\Contracts;

interface VideoProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Create a real video meeting space. Never fake a link.
     *
     * @return array{success: bool, meeting_id: ?string, meeting_url: ?string, message: string}
     */
    public function createMeeting(string $title, \DateTimeInterface $start, \DateTimeInterface $end, ?string $tenantId = null): array;

    /**
     * Delete/cancel a previously created meeting.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteMeeting(string $meetingId): array;
}

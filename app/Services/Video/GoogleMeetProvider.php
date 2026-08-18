<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\VideoProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Meet video provider.
 *
 * Uses the Google Calendar API (with conference data) to create real
 * Meet spaces via Google OAuth. Real API calls are made only when
 * configured with valid OAuth credentials; otherwise the provider
 * gracefully reports "not configured" so the app boots and tests run
 * without live credentials. No fake meeting links are ever generated.
 */
class GoogleMeetProvider implements VideoProviderInterface
{
    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly ?string $refreshToken;

    public function __construct()
    {
        $this->clientId = (string) config('services.google.meet_client_id', '');
        $this->clientSecret = (string) config('services.google.meet_client_secret', '');
        $this->refreshToken = config('services.google.meet_refresh_token');
    }

    public function name(): string
    {
        return 'GOOGLE_MEET';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->refreshToken !== null;
    }

    /**
     * @return array{success: bool, meeting_id: ?string, meeting_url: ?string, message: string}
     */
    public function createMeeting(string $title, \DateTimeInterface $start, \DateTimeInterface $end, ?string $tenantId = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'meeting_id' => null, 'meeting_url' => null, 'message' => 'Google Meet not configured'];
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return ['success' => false, 'meeting_id' => null, 'meeting_url' => null, 'message' => 'Failed to obtain Google access token'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', [
                    'summary' => $title,
                    'start' => ['dateTime' => $start->format('c')],
                    'end' => ['dateTime' => $end->format('c')],
                    'conferenceData' => [
                        'createRequest' => [
                            'requestId' => uniqid('k360-', true),
                            'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $meetingUrl = $data['conferenceData']['entryPoints'][0]['uri'] ?? null;
                $meetingId = $data['id'] ?? null;

                return [
                    'success' => true,
                    'meeting_id' => $meetingId,
                    'meeting_url' => $meetingUrl,
                    'message' => 'Meeting created',
                ];
            }

            Log::warning('Google Meet createMeeting failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'meeting_id' => null, 'meeting_url' => null, 'message' => 'Google API error: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('Google Meet createMeeting exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'meeting_id' => null, 'meeting_url' => null, 'message' => 'API exception'];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteMeeting(string $meetingId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Google Meet not configured'];
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return ['success' => false, 'message' => 'Failed to obtain access token'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$meetingId}");

            if ($response->successful() || $response->status() === 404) {
                return ['success' => true, 'message' => 'Meeting deleted'];
            }

            return ['success' => false, 'message' => 'Delete failed: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('Google Meet deleteMeeting exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'API exception'];
        }
    }

    private function getAccessToken(): ?string
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::warning('Google token refresh failed', ['status' => $response->status()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Google token refresh exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

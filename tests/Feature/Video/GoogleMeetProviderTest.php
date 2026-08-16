<?php

declare(strict_types=1);

namespace Tests\Feature\Video;

use App\Contracts\VideoProviderInterface;
use App\Services\Video\GoogleMeetProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleMeetProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.meet_client_id' => 'test_client_id',
            'services.google.meet_client_secret' => 'test_client_secret',
            'services.google.meet_refresh_token' => 'test_refresh_token',
        ]);
    }

    public function test_provider_is_configured_with_credentials(): void
    {
        $this->assertTrue(app(GoogleMeetProvider::class)->isConfigured());
    }

    public function test_provider_not_configured_without_credentials(): void
    {
        config([
            'services.google.meet_client_id' => null,
            'services.google.meet_client_secret' => null,
            'services.google.meet_refresh_token' => null,
        ]);

        $this->assertFalse(app(GoogleMeetProvider::class)->isConfigured());
    }

    public function test_provider_name(): void
    {
        $this->assertSame('GOOGLE_MEET', app(GoogleMeetProvider::class)->name());
    }

    public function test_video_interface_resolves_to_google_meet(): void
    {
        $provider = app(VideoProviderInterface::class);

        $this->assertInstanceOf(GoogleMeetProvider::class, $provider);
    }

    public function test_create_meeting_returns_not_configured_without_credentials(): void
    {
        config([
            'services.google.meet_client_id' => null,
            'services.google.meet_client_secret' => null,
            'services.google.meet_refresh_token' => null,
        ]);

        $result = app(GoogleMeetProvider::class)->createMeeting(
            'Test Consultation',
            now(),
            now()->addHour()
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Google Meet not configured', $result['message']);
        $this->assertNull($result['meeting_url']);
        $this->assertNull($result['meeting_id']);
    }

    public function test_delete_meeting_returns_not_configured_without_credentials(): void
    {
        config([
            'services.google.meet_client_id' => null,
            'services.google.meet_client_secret' => null,
            'services.google.meet_refresh_token' => null,
        ]);

        $result = app(GoogleMeetProvider::class)->deleteMeeting('meet-123');

        $this->assertFalse($result['success']);
        $this->assertSame('Google Meet not configured', $result['message']);
    }

    public function test_create_meeting_never_returns_fake_url_when_unconfigured(): void
    {
        config([
            'services.google.meet_client_id' => null,
            'services.google.meet_client_secret' => null,
            'services.google.meet_refresh_token' => null,
        ]);

        $result = app(GoogleMeetProvider::class)->createMeeting(
            'Test',
            now(),
            now()->addHour()
        );

        // Verify no fake URL is ever returned when not configured.
        $this->assertNull($result['meeting_url']);
        $this->assertNull($result['meeting_id']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Contracts\OCRProviderInterface;
use App\Contracts\SpeechProviderInterface;
use App\Integrations\OCR\GoogleVisionOcrProvider;
use App\Integrations\OCR\TesseractOcrProvider;
use App\Integrations\Speech\OpenAiWhisperProvider;
use App\Integrations\Speech\WhisperCppProvider;
use App\Models\Document;
use App\Models\Tenant;
use App\Services\Documents\OcrService;
use App\Services\Documents\SpeechService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeOcrProvider;
use Tests\Support\Fakes\FakeSpeechProvider;
use Tests\TestCase;

class OcrAndSpeechProviderTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // No OCR/Speech creds by default → graceful degrade path.
        config(['services.google_vision.api_key' => '']);
        config(['services.openai.api_key' => '']);
    }

    // --- OCR provider interface resolution ---

    public function test_ocr_interface_resolves_to_google_vision_by_default(): void
    {
        config(['services.ocr_provider' => 'google_vision']);

        $this->assertInstanceOf(GoogleVisionOcrProvider::class, app(OCRProviderInterface::class));
    }

    public function test_ocr_interface_resolves_to_tesseract_when_configured(): void
    {
        config(['services.ocr_provider' => 'tesseract']);

        $this->assertInstanceOf(TesseractOcrProvider::class, app(OCRProviderInterface::class));
    }

    public function test_google_vision_ocr_not_configured_without_api_key(): void
    {
        $this->assertFalse(app(GoogleVisionOcrProvider::class)->isConfigured());
    }

    public function test_google_vision_ocr_is_configured_with_api_key(): void
    {
        config(['services.google_vision.api_key' => 'test_key']);

        $this->assertTrue(app(GoogleVisionOcrProvider::class)->isConfigured());
    }

    public function test_google_vision_extract_returns_failure_when_not_configured(): void
    {
        $result = app(GoogleVisionOcrProvider::class)->extract('local', 'missing.png');

        $this->assertFalse($result['success']);
        $this->assertSame('Google Vision OCR provider not configured', $result['message']);
        $this->assertSame('', $result['text']);
    }

    public function test_tesseract_ocr_name(): void
    {
        $this->assertSame('TESSERACT', app(TesseractOcrProvider::class)->name());
    }

    // --- Speech provider interface resolution ---

    public function test_speech_interface_resolves_to_openai_whisper_by_default(): void
    {
        config(['services.speech_provider' => 'openai']);

        $this->assertInstanceOf(OpenAiWhisperProvider::class, app(SpeechProviderInterface::class));
    }

    public function test_speech_interface_resolves_to_whisper_cpp_when_configured(): void
    {
        config(['services.speech_provider' => 'whisper_cpp']);

        $this->assertInstanceOf(WhisperCppProvider::class, app(SpeechProviderInterface::class));
    }

    public function test_openai_whisper_not_configured_without_api_key(): void
    {
        $this->assertFalse(app(OpenAiWhisperProvider::class)->isConfigured());
    }

    public function test_openai_whisper_transcribe_returns_failure_when_not_configured(): void
    {
        $result = app(OpenAiWhisperProvider::class)->transcribe('local', 'missing.wav');

        $this->assertFalse($result['success']);
        $this->assertSame('OpenAI Whisper provider not configured', $result['message']);
    }

    public function test_whisper_cpp_not_configured_without_binary_and_model(): void
    {
        config(['services.whisper_cpp.binary' => '', 'services.whisper_cpp.model' => '']);

        $this->assertFalse(app(WhisperCppProvider::class)->isConfigured());
    }

    // --- OcrService orchestration (degrade path) ---

    public function test_ocr_service_reports_not_configured_when_provider_unconfigured(): void
    {
        $this->assertFalse(app(OcrService::class)->isConfigured());
        $this->assertSame('GOOGLE_VISION', app(OcrService::class)->providerName());
    }

    public function test_ocr_service_extract_returns_failure_and_logs_audit_when_not_configured(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $document = Document::factory()->create();

        $result = app(OcrService::class)->extractFromDocument($document);

        $this->assertFalse($result['success']);
        // Audit log is still recorded even on failure (governance trail).
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.ocr_failed',
            'auditable_type' => 'App\\Models\\Document',
            'auditable_id' => $document->id,
        ]);
        // Metadata NOT stamped on failure.
        $this->assertNull($document->fresh()->metadata['ocr_text'] ?? null);
    }

    public function test_ocr_service_stamps_metadata_and_logs_audit_on_success(): void
    {
        $this->swapOcrProvider(new FakeOcrProvider());

        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $document = Document::factory()->create(['metadata' => ['uploaded_via' => 'web']]);

        $result = app(OcrService::class)->extractFromDocument($document);

        $this->assertTrue($result['success']);
        $this->assertSame('Haemoglobin: 14.2 g/dL', $result['text']);

        $refreshed = $document->fresh();
        $this->assertSame('Haemoglobin: 14.2 g/dL', $refreshed->metadata['ocr_text']);
        $this->assertSame('FAKE_OCR', $refreshed->metadata['ocr_provider']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.ocr_extracted',
            'auditable_type' => 'App\\Models\\Document',
            'auditable_id' => $document->id,
        ]);
    }

    // --- SpeechService orchestration (degrade path) ---

    public function test_speech_service_reports_not_configured_when_provider_unconfigured(): void
    {
        $this->assertFalse(app(SpeechService::class)->isConfigured());
        $this->assertSame('OPENAI_WHISPER', app(SpeechService::class)->providerName());
    }

    public function test_speech_service_transcribe_returns_failure_and_logs_audit_when_not_configured(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $document = Document::factory()->create();

        $result = app(SpeechService::class)->transcribeFromDocument($document);

        $this->assertFalse($result['success']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.speech_transcribe_failed',
            'auditable_type' => 'App\\Models\\Document',
            'auditable_id' => $document->id,
        ]);
        $this->assertNull($document->fresh()->metadata['transcription'] ?? null);
    }

    public function test_speech_service_stamps_metadata_and_logs_audit_on_success(): void
    {
        $this->swapSpeechProvider(new FakeSpeechProvider());

        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $document = Document::factory()->create();

        $result = app(SpeechService::class)->transcribeFromDocument($document);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('joint pain', $result['text']);

        $refreshed = $document->fresh();
        $this->assertSame('Patient reports joint pain for three days.', $refreshed->metadata['transcription']);
        $this->assertSame('FAKE_SPEECH', $refreshed->metadata['transcription_provider']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.speech_transcribed',
            'auditable_type' => 'App\\Models\\Document',
            'auditable_id' => $document->id,
        ]);
    }

    private function swapOcrProvider(OCRProviderInterface $provider): void
    {
        app()->forgetInstance(OcrService::class);
        app()->forgetInstance(OCRProviderInterface::class);
        app()->instance(OCRProviderInterface::class, $provider);
    }

    private function swapSpeechProvider(SpeechProviderInterface $provider): void
    {
        app()->forgetInstance(SpeechService::class);
        app()->forgetInstance(SpeechProviderInterface::class);
        app()->instance(SpeechProviderInterface::class, $provider);
    }
}

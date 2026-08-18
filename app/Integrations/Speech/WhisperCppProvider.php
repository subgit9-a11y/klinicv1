<?php

declare(strict_types=1);

namespace App\Integrations\Speech;

use App\Contracts\SpeechProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Self-hosted whisper.cpp speech-to-text provider.
 *
 * Shells out to a configured `whisper-cli` (whisper.cpp) binary to transcribe
 * audio locally — no clinical audio leaves the server, suitable for strict
 * data-residency deployments. Real transcription happens only when the binary
 * is available; otherwise the provider reports "not configured".
 */
class WhisperCppProvider implements SpeechProviderInterface
{
    private readonly string $binary;

    private readonly string $model;

    public function __construct()
    {
        $this->binary = (string) config('services.whisper_cpp.binary', 'whisper-cli');
        $this->model = (string) config('services.whisper_cpp.model', '');
    }

    public function name(): string
    {
        return 'WHISPER_CPP';
    }

    public function isConfigured(): bool
    {
        if ($this->binary === '' || $this->model === '') {
            return false;
        }

        $check = Process::fromShellCommandline('command -v '.escapeshellarg($this->binary));
        $check->run();

        if (! $check->isSuccessful()) {
            return false;
        }

        return is_readable($this->model);
    }

    /**
     * Transcribe an audio file via whisper.cpp.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function transcribe(string $disk, string $path): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'text' => '', 'message' => 'whisper.cpp not configured'];
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return ['success' => false, 'text' => '', 'message' => 'File not found'];
            }

            $absPath = $this->localAbsolutePath($disk, $path);
            if ($absPath === null) {
                $absPath = $this->materialiseTemp($disk, $path);
            }

            $process = new Process([
                $this->binary,
                '-m', $this->model,
                '-f', $absPath,
                '-nt', // no timestamps in output
            ]);
            $process->run();

            $text = trim($process->getOutput());

            return ['success' => $process->isSuccessful() && $text !== '', 'text' => $text, 'message' => $process->isSuccessful() ? 'ok' : $process->getErrorOutput()];
        } catch (\Throwable $e) {
            Log::error('whisper.cpp exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'text' => '', 'message' => $e->getMessage()];
        }
    }

    private function localAbsolutePath(string $disk, string $path): ?string
    {
        $diskConfig = config("filesystems.disks.{$disk}", []);

        if (($diskConfig['driver'] ?? null) === 'local') {
            $root = realpath($diskConfig['root'] ?? storage_path('app/private'));
            if ($root === false) {
                return null;
            }
            $full = $root.DIRECTORY_SEPARATOR.ltrim($path, '/');

            return is_file($full) ? $full : null;
        }

        return null;
    }

    private function materialiseTemp(string $disk, string $path): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'spch_').'.wav';
        file_put_contents($temp, (string) Storage::disk($disk)->get($path));

        return $temp;
    }
}

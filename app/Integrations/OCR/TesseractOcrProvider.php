<?php

declare(strict_types=1);

namespace App\Integrations\OCR;

use App\Contracts\OCRProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Self-hosted Tesseract OCR provider.
 *
 * Shells out to the `tesseract` binary to extract text from image files on the
 * local/private disk. Real extraction happens only when the binary is available
 * on PATH (or at the configured path); otherwise the provider reports "not
 * configured" and callers degrade gracefully.
 *
 * Suitable for clinics that cannot send clinical images to a cloud OCR API
 * (data-residency / privacy constraints).
 */
class TesseractOcrProvider implements OCRProviderInterface
{
    private readonly string $binary;

    public function __construct()
    {
        $this->binary = (string) config('services.tesseract.binary', 'tesseract');
    }

    public function name(): string
    {
        return 'TESSERACT';
    }

    public function isConfigured(): bool
    {
        if ($this->binary === '') {
            return false;
        }

        // `which` works on Linux/macOS dev + cPanel. Treat a non-zero exit as
        // "binary not present" rather than failing hard.
        $check = Process::fromShellCommandline('command -v '.escapeshellarg($this->binary));
        $check->run();

        return $check->isSuccessful();
    }

    /**
     * Extract text from an image file via Tesseract.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function extract(string $disk, string $path): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'text' => '', 'message' => 'Tesseract binary not available'];
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return ['success' => false, 'text' => '', 'message' => 'File not found'];
            }

            $absPath = $this->localAbsolutePath($disk, $path);
            if ($absPath === null) {
                // For non-local disks, materialise a temp copy (Tesseract needs a real file).
                $absPath = $this->materialiseTemp($disk, $path);
            }

            $process = new Process([
                $this->binary,
                $absPath,
                'stdout', // output to stdout
                '-l', (string) config('services.tesseract.lang', 'eng'),
            ]);
            $process->run();

            $text = trim($process->getOutput());

            return ['success' => $process->isSuccessful() && $text !== '', 'text' => $text, 'message' => $process->isSuccessful() ? 'ok' : $process->getErrorOutput()];
        } catch (\Throwable $e) {
            Log::error('Tesseract OCR exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'text' => '', 'message' => $e->getMessage()];
        }
    }

    /**
     * Resolve the absolute filesystem path for a local-disk file, or null.
     */
    private function localAbsolutePath(string $disk, string $path): ?string
    {
        $diskConfig = config("filesystems.disks.{$disk}", []);

        // Only the 'local' driver (and 'public' on cPanel) exposes a real root path.
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
        $temp = tempnam(sys_get_temp_dir(), 'ocr_').'.img';
        file_put_contents($temp, (string) Storage::disk($disk)->get($path));

        return $temp;
    }
}

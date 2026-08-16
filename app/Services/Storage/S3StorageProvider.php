<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\StorageProviderInterface;
use Illuminate\Support\Facades\Storage;

/**
 * S3 storage provider with signed URL support.
 *
 * Uses Laravel's Storage facade with the 's3' disk. When configured,
 * generates temporary signed URLs for private file access. Falls back
 * to the local disk for development/testing.
 */
class S3StorageProvider implements StorageProviderInterface
{
    private readonly string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default') === 's3' ? 's3' : 'local';
    }

    public function name(): string
    {
        return $this->disk === 's3' ? 'S3' : 'LOCAL';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function store(string $content, string $path, ?string $mimeType = null): string
    {
        $options = $mimeType !== null ? ['mimetype' => $mimeType] : [];
        Storage::disk($this->disk)->put($path, $content, $options);

        return $path;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiry): string
    {
        if ($this->disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($path, $expiry);
        }

        // Local disk: return a signed URL. The actual access control
        // is enforced by the controller middleware. We sign the path
        // parameter so the URL can't be tampered with.
        return url()->temporarySignedRoute(
            'documents.stream',
            $expiry,
            ['path' => base64_encode($path)]
        );
    }

    /**
     * @return resource
     */
    public function stream(string $path)
    {
        return Storage::disk($this->disk)->readStream($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}

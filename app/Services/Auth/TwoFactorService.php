<?php

declare(strict_types=1);

namespace App\Services\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly Encrypter $encrypter,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function encrypt(string $secret): string
    {
        return $this->encrypter->encrypt($secret);
    }

    public function decrypt(string $encryptedSecret): string
    {
        return $this->encrypter->decrypt($encryptedSecret);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, trim($code));
    }

    public function qrCodeSvg(string $company, string $holder, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl($company, $holder, $secret);
        $writer = new Writer(
            new ImageRenderer(new RendererStyle(256), new SvgImageBackEnd)
        );

        // Strip the XML prolog so the SVG embeds cleanly as inline HTML within
        // the Blade view and Livewire's morphdom snapshot.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($url));
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::random(10).'-'.Str::random(10))
            ->all();
    }
}

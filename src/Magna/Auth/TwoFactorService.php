<?php

declare(strict_types=1);

namespace Magna\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Config;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function getQrCodeUrl(string $email, string $secret): string
    {
        return $this->engine->getQRCodeUrl(
            Config::string('magna.two_factor.issuer', 'Magna CMS'),
            $email,
            $secret,
        );
    }

    /** Returns an inline SVG string for the enrollment QR code. */
    public function getQrCodeSvg(string $email, string $secret): string
    {
        $url = $this->getQrCodeUrl($email, $secret);

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(192),
                new SvgImageBackEnd,
            )
        );

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->engine->verifyKey($secret, $code);
    }

    /**
     * Generate a set of one-time recovery codes.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(?int $count = null): array
    {
        $count ??= Config::integer('magna.two_factor.recovery_codes', 8);

        return array_map(
            fn (): string => sprintf('%s-%s', bin2hex(random_bytes(5)), bin2hex(random_bytes(5))),
            range(1, $count),
        );
    }

    /**
     * Attempt to redeem a recovery code. Returns the remaining codes on
     * success, or false if no code matched.
     *
     * @param  list<string>  $codes
     * @return list<string>|false
     */
    public function redeemRecoveryCode(array $codes, string $input): array|false
    {
        foreach ($codes as $index => $code) {
            if (hash_equals($code, $input)) {
                unset($codes[$index]);

                return array_values($codes);
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use InvalidArgumentException;

/**
 * Request-signing credentials for a Widevine/PlayReady key server: either an
 * AES key+IV pair or an RSA private key path, never both. Making the two
 * mutually exclusive at construction removes the need to defer that check to
 * CommandBuilder::build() time.
 */
final readonly class SigningCredentials
{
    private function __construct(
        public ?string $aesSigningKey,
        public ?string $aesSigningIv,
        public ?string $rsaSigningKeyPath,
    ) {}

    public static function aes(string $key, string $iv): self
    {
        self::assertHex('AES signing key', $key);
        self::assertHex('AES signing IV', $iv);

        return new self($key, $iv, null);
    }

    public static function rsa(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('RSA signing key path must not be empty');
        }

        return new self(null, null, $path);
    }

    public function isAes(): bool
    {
        return $this->aesSigningKey !== null;
    }

    /**
     * @return array<string, string>
     */
    public function toOptions(): array
    {
        return $this->isAes()
            ? ['aes_signing_key' => $this->aesSigningKey, 'aes_signing_iv' => $this->aesSigningIv]
            : ['rsa_signing_key_path' => $this->rsaSigningKeyPath];
    }

    private static function assertHex(string $label, string $hex): void
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException("{$label} must be a non-empty hex string");
        }
    }
}

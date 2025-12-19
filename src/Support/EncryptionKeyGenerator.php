<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Illuminate\Support\Facades\Storage;

class EncryptionKeyGenerator
{
    /**
     * Generate a 128-bit (16 bytes) encryption key for HLS
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a 128-bit (16 bytes) key ID for HLS
     */
    public static function generateKeyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate both key and key ID
     */
    public static function generate(): array
    {
        return [
            'key_id' => self::generateKeyId(),
            'key' => self::generateKey(),
        ];
    }

    /**
     * Format encryption config for Shaka Packager
     */
    public static function formatForShaka(string $keyId, string $key, ?string $label = ''): string
    {
        return sprintf('label=%s:key_id=%s:key=%s', $label, $keyId, $key);
    }

    /**
     * Write encryption key to disk
     */
    public static function writeKeyFile(string $disk, string $path, string $key): void
    {
        Storage::disk($disk)->put($path, hex2bin($key));
    }
}

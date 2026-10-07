<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Media\Filters\Number;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * Typed and validated Shaka Packager options the generic packaging builder has no method for:
 * DRM systems, live and low-latency DASH, base URLs and segment numbering. Pass them to
 * the builder with withOptions(ShakaOptions::make()->...).
 *
 * @see https://shaka-project.github.io/shaka-packager/html/documentation.html
 *
 * @implements Arrayable<string, string|bool>
 */
final class ShakaOptions implements Arrayable
{
    /** @var array<string, string|bool> */
    protected array $options = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * URLs written into the DASH manifest as BaseURL elements, e.g. a CDN.
     *
     * @param  string|list<string>  $urls
     */
    public function baseUrls(string|array $urls): self
    {
        $urls = (array) $urls;

        if ($urls === [] || in_array('', $urls, true)) {
            throw new InvalidArgumentException('The base URLs must not be empty.');
        }

        return $this->set('base_urls', implode(',', $urls));
    }

    /**
     * A URL prefix for the media playlists and segments in HLS playlists.
     */
    public function hlsBaseUrl(string $url): self
    {
        return $this->set('hls_base_url', $this->filled('HLS base URL', $url));
    }

    public function hlsMediaSequenceNumber(int $number): self
    {
        return $this->set('hls_media_sequence_number', (string) $this->atLeast('HLS media sequence number', $number, 0));
    }

    /**
     * Where players start in a live HLS playlist, in seconds; negative counts back from the end.
     */
    public function hlsStartTimeOffset(float $seconds): self
    {
        return $this->set('hls_start_time_offset', Number::format($seconds));
    }

    /**
     * Add EXT-X-SESSION-KEY tags to the HLS master playlist, so players can load keys earlier.
     */
    public function createSessionKeys(bool $enabled = true): self
    {
        return $this->set('create_session_keys', $enabled);
    }

    public function startSegmentNumber(int $number): self
    {
        return $this->set('start_segment_number', (string) $this->atLeast('start segment number', $number, 0));
    }

    public function transportStreamTimestampOffset(int $milliseconds): self
    {
        return $this->set('transport_stream_timestamp_offset_ms', (string) $this->atLeast('transport stream timestamp offset', $milliseconds, 0));
    }

    /**
     * Write a static DASH manifest for live profile content, e.g. to keep a recording.
     */
    public function generateStaticLiveMpd(bool $enabled = true): self
    {
        return $this->set('generate_static_live_mpd', $enabled);
    }

    public function minBufferTime(float $seconds): self
    {
        return $this->set('min_buffer_time', $this->seconds('minimum buffer time', $seconds));
    }

    public function minimumUpdatePeriod(float $seconds): self
    {
        return $this->set('minimum_update_period', $this->seconds('minimum update period', $seconds));
    }

    public function suggestedPresentationDelay(float $seconds): self
    {
        return $this->set('suggested_presentation_delay', $this->seconds('suggested presentation delay', $seconds));
    }

    /**
     * How far back viewers can seek in a live stream.
     */
    public function timeShiftBufferDepth(float $seconds): self
    {
        return $this->set('time_shift_buffer_depth', $this->seconds('time shift buffer depth', $seconds));
    }

    public function preservedSegmentsOutsideLiveWindow(int $segments): self
    {
        return $this->set('preserved_segments_outside_live_window', (string) $this->atLeast('preserved segments', $segments, 0));
    }

    /**
     * UTCTiming elements for live DASH clock sync, as scheme ID URI => value.
     *
     * @param  array<string, string>  $timings  e.g. ['urn:mpeg:dash:utc:http-xsdate:2014' => 'https://time.akamai.com/?iso']
     */
    public function utcTimings(array $timings): self
    {
        if ($timings === []) {
            throw new InvalidArgumentException('The UTC timings must not be empty.');
        }

        return $this->set('utc_timings', implode(',', array_map(
            fn (string $scheme, string $value): string => "{$scheme}={$value}",
            array_keys($timings),
            $timings,
        )));
    }

    public function lowLatencyDashMode(bool $enabled = true): self
    {
        return $this->set('low_latency_dash_mode', $enabled);
    }

    /**
     * Always write the Content Length index (sidx) in DASH segments.
     */
    public function forceClIndex(bool $enabled = true): self
    {
        return $this->set('force_cl_index', $enabled);
    }

    /**
     * Encrypt this many 16-byte blocks of each pattern (cbcs and cens).
     */
    public function cryptByteBlock(int $blocks): self
    {
        return $this->set('crypt_byte_block', (string) $this->atLeast('crypt byte block', $blocks, 0));
    }

    /**
     * Leave this many 16-byte blocks of each pattern clear (cbcs and cens).
     */
    public function skipByteBlock(int $blocks): self
    {
        return $this->set('skip_byte_block', (string) $this->atLeast('skip byte block', $blocks, 0));
    }

    public function vp9SubsampleEncryption(bool $enabled = true): self
    {
        unset($this->options['vp9_subsample_encryption'], $this->options['novp9_subsample_encryption']);

        return $this->set($enabled ? 'vp9_subsample_encryption' : 'novp9_subsample_encryption', true);
    }

    /**
     * Signal these protection systems in the manifests, e.g. protectionSystems(ProtectionSystem::Widevine, ProtectionSystem::PlayReady).
     */
    public function protectionSystems(ProtectionSystem ...$systems): self
    {
        if ($systems === []) {
            throw new InvalidArgumentException('Pass at least one protection system.');
        }

        return $this->set('protection_systems', implode(',', array_map(
            fn (ProtectionSystem $system): string => $system->value,
            array_values(array_unique($systems, SORT_REGULAR)),
        )));
    }

    public function playreadyExtraHeaderData(string $xml): self
    {
        return $this->set('playready_extra_header_data', $this->filled('PlayReady extra header data', $xml));
    }

    /**
     * Use this initialisation vector instead of a random one (hex, 16 or 32 characters). Only for testing.
     */
    public function iv(string $hex): self
    {
        return $this->set('iv', $this->hex('IV', $hex));
    }

    /**
     * Add this PSSH box to the output (hex). Raw key encryption only.
     */
    public function pssh(string $hex): self
    {
        return $this->set('pssh', $this->hex('PSSH', $hex));
    }

    /**
     * Encrypt with keys from a Widevine key server.
     */
    public function widevine(string $keyServerUrl, string $contentId, ?string $policy = null): self
    {
        return $this->set('enable_widevine_encryption', true)
            ->set('key_server_url', $this->filled('key server URL', $keyServerUrl))
            ->set('content_id', $this->hex('content ID', $contentId))
            ->when($policy !== null, fn () => $this->set('policy', $this->filled('policy', (string) $policy)));
    }

    public function enableEntitlementLicense(bool $enabled = true): self
    {
        return $this->set('enable_entitlement_license', $enabled);
    }

    /**
     * Widevine resolution thresholds that decide which tracks share a key.
     */
    public function maxPixels(?int $sd = null, ?int $hd = null, ?int $uhd1 = null): self
    {
        foreach (['max_sd_pixels' => $sd, 'max_hd_pixels' => $hd, 'max_uhd1_pixels' => $uhd1] as $option => $pixels) {
            if ($pixels !== null) {
                $this->set($option, (string) $this->atLeast(str_replace('_', ' ', $option), $pixels, 1));
            }
        }

        return $this;
    }

    public function groupId(string $hex): self
    {
        return $this->set('group_id', $this->hex('group ID', $hex));
    }

    /**
     * Sign key requests with AES. Replaces RSA signing.
     */
    public function aesSigning(string $signer, string $key, string $iv): self
    {
        unset($this->options['rsa_signing_key_path']);

        return $this->set('signer', $this->filled('signer', $signer))
            ->set('aes_signing_key', $this->hex('AES signing key', $key))
            ->set('aes_signing_iv', $this->hex('AES signing IV', $iv));
    }

    /**
     * Sign key requests with an RSA key file. Replaces AES signing.
     */
    public function rsaSigning(string $signer, string $keyPath): self
    {
        unset($this->options['aes_signing_key'], $this->options['aes_signing_iv']);

        return $this->set('signer', $this->filled('signer', $signer))
            ->set('rsa_signing_key_path', $this->filled('RSA signing key path', $keyPath));
    }

    /**
     * Encrypt with keys from a PlayReady key server.
     */
    public function playready(string $serverUrl, ?string $programIdentifier = null): self
    {
        return $this->set('enable_playready_encryption', true)
            ->set('playready_server_url', $this->filled('PlayReady server URL', $serverUrl))
            ->when($programIdentifier !== null, fn () => $this->set('program_identifier', $this->filled('program identifier', (string) $programIdentifier)));
    }

    /**
     * TLS settings for requests to the key servers.
     */
    public function keyServerTls(?string $caFile = null, ?string $clientCertFile = null, ?string $clientCertPrivateKeyFile = null, ?string $clientCertPrivateKeyPassword = null): self
    {
        foreach ([
            'ca_file' => $caFile,
            'client_cert_file' => $clientCertFile,
            'client_cert_private_key_file' => $clientCertPrivateKeyFile,
            'client_cert_private_key_password' => $clientCertPrivateKeyPassword,
        ] as $option => $value) {
            if ($value !== null) {
                $this->set($option, $this->filled(str_replace('_', ' ', $option), $value));
            }
        }

        return $this;
    }

    /**
     * Decrypt encrypted input with a raw key (set the key with the "keys" option) or a Widevine key server.
     */
    public function decrypt(bool $widevine = false): self
    {
        return $this->set($widevine ? 'enable_widevine_decryption' : 'enable_raw_key_decryption', true);
    }

    public function toArray(): array
    {
        return $this->options;
    }

    protected function set(string $option, string|bool $value): self
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * @param  callable(): self  $callback
     */
    protected function when(bool $condition, callable $callback): self
    {
        return $condition ? $callback() : $this;
    }

    protected function filled(string $name, string $value): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("The {$name} must not be empty.");
        }

        return $value;
    }

    protected function hex(string $name, string $value): string
    {
        if ($value === '' || strlen($value) % 2 !== 0 || ! ctype_xdigit($value)) {
            throw new InvalidArgumentException("The {$name} must be hexadecimal.");
        }

        return $value;
    }

    protected function atLeast(string $name, int $value, int $minimum): int
    {
        if ($value < $minimum) {
            throw new InvalidArgumentException("The {$name} must be at least {$minimum}.");
        }

        return $value;
    }

    protected function seconds(string $name, float $seconds): string
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException("The {$name} can't be negative.");
        }

        return Number::format($seconds);
    }
}

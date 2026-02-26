<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Exceptions\InvalidStreamConfigurationException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builder for constructing Shaka Packager command arguments
 */
class CommandBuilder
{
    protected Collection $streams;

    protected array $options = [];

    public function __construct()
    {
        $this->streams = Collection::make();
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Add a stream configuration directly
     *
     * Accepts the Shaka format (in, stream, output) and validates it
     * before adding to the streams collection.
     *
     * @throws InvalidStreamConfigurationException
     */
    public function addStream(array $stream): self
    {
        StreamValidator::validate($stream);

        $this->streams->push($stream);

        return $this;
    }

    public function addVideoStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'video',
            'output' => $output,
        ], $options));
    }

    public function addAudioStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'audio',
            'output' => $output,
        ], $options));
    }

    public function addTextStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'text',
            'output' => $output,
        ], $options));
    }

    public function withMpdOutput(string $path): self
    {
        $this->options['mpd_output'] = $path;

        return $this;
    }

    public function withHlsMasterPlaylist(string $path): self
    {
        $this->options['hls_master_playlist_output'] = $path;

        return $this;
    }

    /**
     * Set the base URL prefix for HLS Media Playlists and media files.
     */
    public function withHlsBaseUrl(string $url): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('HLS base URL must not be empty');
        }

        $this->options['hls_base_url'] = $url;

        return $this;
    }

    /**
     * Set the key URI for 'identity' and FairPlay key formats.
     *
     * Ignored when the playlist is not encrypted or uses a different key format.
     */
    public function withHlsKeyUri(string $uri): self
    {
        if ($uri === '') {
            throw new InvalidArgumentException('HLS key URI must not be empty');
        }

        $this->options['hls_key_uri'] = $uri;

        return $this;
    }

    /**
     * Set the HLS playlist type (EXT-X-PLAYLIST-TYPE).
     *
     * Accepted values: 'VOD', 'EVENT', 'LIVE'.
     * For LIVE, the EXT-X-PLAYLIST-TYPE tag is omitted entirely.
     */
    public function withHlsPlaylistType(string $type): self
    {
        $type = strtoupper($type);

        $allowed = ['VOD', 'EVENT', 'LIVE'];

        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(
                'HLS playlist type must be one of: '.implode(', ', $allowed).". Got: {$type}"
            );
        }

        $this->options['hls_playlist_type'] = $type;

        return $this;
    }

    /**
     * Set the initial EXT-X-MEDIA-SEQUENCE value for live HLS playlists.
     *
     * Useful when restarting the packager mid-stream so segment numbering
     * continues from a previous run rather than resetting to zero.
     */
    public function withHlsMediaSequenceNumber(int $number): self
    {
        if ($number < 0) {
            throw new InvalidArgumentException('HLS media sequence number must be non-negative');
        }

        $this->options['hls_media_sequence_number'] = $number;

        return $this;
    }

    /**
     * Set EXT-X-START on HLS media playlists.
     *
     * A positive value is an offset from the start of the playlist;
     * a negative value is an offset from the end of the last segment.
     */
    public function withHlsStartTimeOffset(float|int $seconds): self
    {
        $this->options['hls_start_time_offset'] = $seconds;

        return $this;
    }

    /**
     * Restrict output to HLS only (0 = disabled, 1 = enabled).
     */
    public function withHlsOnly(bool $enabled = true): self
    {
        $this->options['hls_only'] = $enabled ? 1 : 0;

        return $this;
    }

    /**
     * Emit EXT-X-SESSION-KEY in the master playlist for offline HLS playback.
     *
     * Required when content keys need to be declared up-front for offline/download
     * scenarios per the HLS specification.
     */
    public function withCreateSessionKeys(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['create_session_keys'] = true;
        } else {
            $this->removeOption('create_session_keys');
        }

        return $this;
    }

    public function withSegmentDuration(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Segment duration must be at least 1 second');
        }

        $this->options['segment_duration'] = $seconds;

        return $this;
    }

    /**
     * Set fragment duration in seconds.
     *
     * Should not be larger than the segment duration. Actual fragment durations
     * may not be exactly as requested.
     */
    public function withFragmentDuration(float|int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Fragment duration must be greater than 0');
        }

        $this->options['fragment_duration'] = $seconds;

        return $this;
    }

    /**
     * Set the start segment number for DASH SegmentTemplate and HLS segment names.
     */
    public function withStartSegmentNumber(int $number): self
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Start segment number must be non-negative');
        }

        $this->options['start_segment_number'] = $number;

        return $this;
    }

    /**
     * Set the transport stream timestamp offset in milliseconds.
     *
     * Applies to MPEG2-TS and HLS Packed Audio outputs. A positive value offsets
     * output timestamps to compensate for possible negative timestamps in input.
     * Default: 100ms.
     */
    public function withTransportStreamTimestampOffsetMs(int $ms): self
    {
        if ($ms <= 0) {
            throw new InvalidArgumentException('Transport stream timestamp offset must be a positive value (> 0)');
        }

        $this->options['transport_stream_timestamp_offset_ms'] = $ms;

        return $this;
    }

    /**
     * Generate a static MPD even when segment_template is specified.
     *
     * By default a dynamic MPD is generated when using segment templates;
     * enabling this flag forces a static MPD instead.
     */
    public function withGenerateStaticLiveMpd(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['generate_static_live_mpd'] = true;
        } else {
            $this->removeOption('generate_static_live_mpd');
        }

        return $this;
    }

    /**
     * Set the minimum buffer time (in seconds) for the DASH MPD.
     *
     * Specifies a common duration used in the definition of the MPD
     * Representation data rate.
     */
    public function withMinBufferTime(float|int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Min buffer time must be non-negative');
        }

        $this->options['min_buffer_time'] = $seconds;

        return $this;
    }

    /**
     * Set how often (in seconds) players should refresh the MPD.
     *
     * Used for dynamic MPD only. Must be a positive value — a zero period
     * would instruct players to refresh constantly.
     */
    public function withMinimumUpdatePeriod(float|int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Minimum update period must be greater than 0');
        }

        $this->options['minimum_update_period'] = $seconds;

        return $this;
    }

    /**
     * Set the suggested presentation delay in seconds for a dynamic MPD.
     *
     * Specifies a delay to be added to the media presentation time.
     */
    public function withSuggestedPresentationDelay(float|int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Suggested presentation delay must be non-negative');
        }

        $this->options['suggested_presentation_delay'] = $seconds;

        return $this;
    }

    /**
     * Set the time shift buffer depth in seconds for dynamic presentations.
     *
     * Guaranteed duration of the time shifting buffer. Also applies to HLS live playlists.
     * Must be positive — a zero depth is meaningless for a live time-shift window.
     */
    public function withTimeShiftBufferDepth(float|int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Time shift buffer depth must be greater than 0');
        }

        $this->options['time_shift_buffer_depth'] = $seconds;

        return $this;
    }

    /**
     * Set the number of segments to preserve outside the live window.
     *
     * Segments outside the live window are normally removed; this keeps the most
     * recent N segments accessible to accommodate pipeline latencies.
     * Pass 0 to disable removal entirely.
     */
    public function withPreservedSegmentsOutsideLiveWindow(int $numSegments): self
    {
        if ($numSegments < 0) {
            throw new InvalidArgumentException('Preserved segments count must be non-negative');
        }

        $this->options['preserved_segments_outside_live_window'] = $numSegments;

        return $this;
    }

    /**
     * Set UTCTiming scheme/value pairs for the dynamic MPD.
     *
     * Format: "<scheme_id_uri>=<value>[,<scheme_id_uri>=<value>]…"
     * Example: "urn:mpeg:dash:utc:http-xsdate:2014=https://time.example.com"
     */
    public function withUtcTimings(string $schemeIdUriValuePairs): self
    {
        if ($schemeIdUriValuePairs === '') {
            throw new InvalidArgumentException('UTCTiming pairs must not be empty');
        }

        // Each pair must follow the <scheme_id_uri>=<value> format
        foreach (explode(',', $schemeIdUriValuePairs) as $pair) {
            if (! str_contains($pair, '=')) {
                throw new InvalidArgumentException(
                    "Invalid UTCTiming pair \"{$pair}\": expected <scheme_id_uri>=<value> format"
                );
            }
        }

        $this->options['utc_timings'] = $schemeIdUriValuePairs;

        return $this;
    }

    /**
     * Set the default language for audio/text tracks.
     *
     * Tracks tagged with this language will receive a "main" role in the manifest,
     * helping players pick the correct default. Can be overridden for text tracks
     * by withDefaultTextLanguage().
     */
    public function withDefaultLanguage(string $language): self
    {
        if ($language === '') {
            throw new InvalidArgumentException('Default language must not be empty');
        }

        $this->options['default_language'] = $language;

        return $this;
    }

    /**
     * Set the default language for text tracks only.
     *
     * Overrides withDefaultLanguage() for text tracks.
     */
    public function withDefaultTextLanguage(string $language): self
    {
        if ($language === '') {
            throw new InvalidArgumentException('Default text language must not be empty');
        }

        $this->options['default_text_language'] = $language;

        return $this;
    }

    /**
     * Allow approximate segment timeline for live DASH profiles.
     *
     * Segments with durations differing by less than one sample are treated as
     * equal, reducing SegmentTimeline entries. When all segments share the same
     * duration (except the last), SegmentTemplate@duration is used and the
     * SegmentTimeline is omitted entirely. Ignored when $Time$ is in the template.
     */
    public function withAllowApproximateSegmentTimeline(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['allow_approximate_segment_timeline'] = true;
        } else {
            $this->removeOption('allow_approximate_segment_timeline');
        }

        return $this;
    }

    /**
     * Restrict output to DASH only (0 = disabled, 1 = enabled).
     */
    public function withDashOnly(bool $enabled = true): self
    {
        $this->options['dash_only'] = $enabled ? 1 : 0;

        return $this;
    }

    /**
     * Allow adaptive codec switching within DASH adaptation sets.
     *
     * When enabled, streams that share language, media type, and container type
     * but differ in codec may be grouped in the same adaptation set.
     */
    public function withAllowCodecSwitching(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['allow_codec_switching'] = true;
        } else {
            $this->removeOption('allow_codec_switching');
        }

        return $this;
    }

    /**
     * Enable LL-DASH (Low Latency DASH) streaming mode.
     *
     * Decouples latency from segment duration, significantly reducing overall
     * end-to-end latency for live streams.
     */
    public function withLowLatencyDashMode(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['low_latency_dash_mode'] = true;
        } else {
            $this->removeOption('low_latency_dash_mode');
        }

        return $this;
    }

    /**
     * Force streams to be ordered in the muxer as given on the command line.
     *
     * When enabled, streams are muxed in CLI order; when disabled, the previous
     * unordered behaviour is used.
     */
    public function withForceClIndex(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['force_cl_index'] = true;
        } else {
            $this->removeOption('force_cl_index');
        }

        return $this;
    }

    /**
     * Set a label for DASH adaptation set grouping.
     *
     * The label is added to the AdaptationSet element and is considered alongside
     * codec, language, media type, and container type when forming adaptation sets.
     */
    public function withDashLabel(string $label): self
    {
        if ($label === '') {
            throw new InvalidArgumentException('DASH label must not be empty');
        }

        $this->options['dash_label'] = $label;

        return $this;
    }

    public function withEncryption(array $encryptionConfig): self
    {
        foreach ($encryptionConfig as $key => $value) {
            $this->options[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the protection scheme.
     *
     * Accepted values: 'cenc' (AES-CTR), 'cbc1', 'cens', 'cbcs' (AES-CBC).
     * Pattern-based schemes ('cens', 'cbcs') apply to video streams only.
     */
    public function withProtectionScheme(string $scheme): self
    {
        $scheme = strtolower($scheme);

        $allowed = ['cenc', 'cbc1', 'cens', 'cbcs'];

        if (! in_array($scheme, $allowed, true)) {
            throw new InvalidArgumentException(
                'Protection scheme must be one of: '.implode(', ', $allowed).". Got: {$scheme}"
            );
        }

        $this->options['protection_scheme'] = $scheme;

        return $this;
    }

    /**
     * Set the count of encrypted 16-byte blocks in the protection pattern.
     *
     * Common patterns (crypt_byte_block:skip_byte_block): 1:9 (default), 5:5, 10:0.
     * Applies to video streams with 'cbcs' and 'cens' schemes only; ignored otherwise.
     */
    public function withCryptByteBlock(int $count): self
    {
        if ($count < 0 || $count > 15) {
            throw new InvalidArgumentException('Crypt byte block must be between 0 and 15');
        }

        $this->options['crypt_byte_block'] = $count;

        return $this;
    }

    /**
     * Set the count of unencrypted 16-byte blocks in the protection pattern.
     *
     * Applies to video streams with 'cbcs' and 'cens' schemes only; ignored otherwise.
     */
    public function withSkipByteBlock(int $count): self
    {
        if ($count < 0 || $count > 15) {
            throw new InvalidArgumentException('Skip byte block must be between 0 and 15');
        }

        $this->options['skip_byte_block'] = $count;

        return $this;
    }

    /**
     * Enable or disable VP9 subsample encryption.
     *
     * Enabled by default. Passing false emits --novp9_subsample_encryption.
     */
    public function withVp9SubsampleEncryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->removeOption('novp9_subsample_encryption');
            $this->options['vp9_subsample_encryption'] = true;
        } else {
            $this->removeOption('vp9_subsample_encryption');
            $this->options['novp9_subsample_encryption'] = true;
        }

        return $this;
    }

    /**
     * Set the clear lead duration in seconds.
     *
     * Segments within this initial window are unencrypted. Default: 5 seconds.
     */
    public function withClearLead(float|int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Clear lead must be non-negative');
        }

        $this->options['clear_lead'] = $seconds;

        return $this;
    }

    /**
     * Specify which protection systems to generate.
     *
     * Comma-separated list of system names: Widevine, PlayReady, FairPlay, Marlin, CommonSystem.
     */
    public function withProtectionSystems(string $systems): self
    {
        if ($systems === '') {
            throw new InvalidArgumentException('Protection systems must not be empty');
        }

        $this->options['protection_systems'] = $systems;

        return $this;
    }

    /**
     * Set extra XML data to append to the PlayReady PSSH.
     *
     * Can be specified even when using a different key source.
     */
    public function withPlayreadyExtraHeaderData(string $xml): self
    {
        if ($xml === '') {
            throw new InvalidArgumentException('PlayReady extra header data must not be empty');
        }

        $this->options['playready_extra_header_data'] = $xml;

        return $this;
    }

    /**
     * Enable encryption with raw keys provided on the command line.
     *
     * Generates a Common protection system unless --pssh or --protection_systems
     * is also specified.
     */
    public function withEnableRawKeyEncryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_raw_key_encryption'] = true;
        } else {
            $this->removeOption('enable_raw_key_encryption');
        }

        return $this;
    }

    /**
     * Enable decryption with raw keys provided on the command line.
     */
    public function withEnableRawKeyDecryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_raw_key_decryption'] = true;
        } else {
            $this->removeOption('enable_raw_key_decryption');
        }

        return $this;
    }

    /**
     * Set raw key info string(s) for encryption or decryption.
     *
     * Format: "label=<label>:key_id=<key_id>:key=<key>[:iv=<iv>][,...]"
     * key_id and key must be 32-digit hex strings.
     */
    public function withKeys(string $keyInfoString): self
    {
        if ($keyInfoString === '') {
            throw new InvalidArgumentException('Keys info string must not be empty');
        }

        $this->options['keys'] = $keyInfoString;

        return $this;
    }

    /**
     * Set the initialization vector in hex format.
     *
     * Must be 16 hex digits (8 bytes) or 32 hex digits (16 bytes).
     * Should only be used for testing — a random IV is generated when omitted.
     * Mutually exclusive with per-key IV specified inside --keys.
     */
    public function withIv(string $hex): self
    {
        if (! preg_match('/^[0-9a-fA-F]+$/', $hex) || ! in_array(strlen($hex), [16, 32], true)) {
            throw new InvalidArgumentException('IV must be a 16-digit or 32-digit hex string (8 or 16 bytes)');
        }

        $this->options['iv'] = $hex;

        return $this;
    }

    /**
     * Set one or more concatenated PSSH boxes in hex string format.
     *
     * When neither this nor --protection_systems is specified, a v1 common PSSH
     * box is generated automatically.
     */
    public function withPssh(string $hex): self
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException('PSSH must be a non-empty hex string');
        }

        $this->options['pssh'] = $hex;

        return $this;
    }

    /**
     * Enable encryption with the Widevine key server.
     *
     * Requires either --aes_signing_key/--aes_signing_iv or --rsa_signing_key_path.
     */
    public function withEnableWidevineEncryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_widevine_encryption'] = true;
        } else {
            $this->removeOption('enable_widevine_encryption');
        }

        return $this;
    }

    /**
     * Enable entitlement license in the Widevine encryption request.
     */
    public function withEnableEntitlementLicense(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_entitlement_license'] = true;
        } else {
            $this->removeOption('enable_entitlement_license');
        }

        return $this;
    }

    /**
     * Enable decryption with the Widevine key server.
     */
    public function withEnableWidevineDecryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_widevine_decryption'] = true;
        } else {
            $this->removeOption('enable_widevine_decryption');
        }

        return $this;
    }

    /**
     * Set the key server URL (required for Widevine encryption and decryption).
     */
    public function withKeyServerUrl(string $url): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('Key server URL must not be empty');
        }

        $this->options['key_server_url'] = $url;

        return $this;
    }

    /**
     * Set the content identifier that uniquely identifies the content (hex string).
     */
    public function withContentId(string $hex): self
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException('Content ID must be a non-empty hex string');
        }

        $this->options['content_id'] = $hex;

        return $this;
    }

    /**
     * Set the name of a stored Widevine policy.
     */
    public function withPolicy(string $policy): self
    {
        if ($policy === '') {
            throw new InvalidArgumentException('Policy must not be empty');
        }

        $this->options['policy'] = $policy;

        return $this;
    }

    /**
     * Set the maximum pixels per frame threshold for SD tracks.
     *
     * Default: 442368 (768 x 576).
     */
    public function withMaxSdPixels(int $pixels): self
    {
        if ($pixels < 1) {
            throw new InvalidArgumentException('Max SD pixels must be greater than 0');
        }

        $this->options['max_sd_pixels'] = $pixels;

        return $this;
    }

    /**
     * Set the maximum pixels per frame threshold for HD tracks.
     *
     * Default: 2073600 (1920 x 1080).
     */
    public function withMaxHdPixels(int $pixels): self
    {
        if ($pixels < 1) {
            throw new InvalidArgumentException('Max HD pixels must be greater than 0');
        }

        $this->options['max_hd_pixels'] = $pixels;

        return $this;
    }

    /**
     * Set the maximum pixels per frame threshold for UHD1 tracks.
     *
     * Default: 8847360 (4096 x 2160).
     */
    public function withMaxUhd1Pixels(int $pixels): self
    {
        if ($pixels < 1) {
            throw new InvalidArgumentException('Max UHD1 pixels must be greater than 0');
        }

        $this->options['max_uhd1_pixels'] = $pixels;

        return $this;
    }

    /**
     * Set the signer name.
     */
    public function withSigner(string $signer): self
    {
        if ($signer === '') {
            throw new InvalidArgumentException('Signer must not be empty');
        }

        $this->options['signer'] = $signer;

        return $this;
    }

    /**
     * Set the AES signing key (hex string).
     *
     * Requires withAesSigningIv() to also be set. Mutually exclusive with
     * withRsaSigningKeyPath().
     */
    public function withAesSigningKey(string $hex): self
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException('AES signing key must be a non-empty hex string');
        }

        $this->options['aes_signing_key'] = $hex;

        return $this;
    }

    /**
     * Set the AES signing IV (hex string).
     *
     * Must be provided whenever withAesSigningKey() is set.
     */
    public function withAesSigningIv(string $hex): self
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException('AES signing IV must be a non-empty hex string');
        }

        $this->options['aes_signing_iv'] = $hex;

        return $this;
    }

    /**
     * Set the path to the PKCS#1 RSA private key file for request signing.
     *
     * Mutually exclusive with withAesSigningKey().
     */
    public function withRsaSigningKeyPath(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('RSA signing key path must not be empty');
        }

        $this->options['rsa_signing_key_path'] = $path;

        return $this;
    }

    /**
     * Set the key rotation period in seconds.
     *
     * When non-zero, key rotation is enabled. Requires a compatible protection
     * scheme ('cenc' or 'cbcs').
     */
    public function withCryptoPeriodDuration(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Crypto period duration must be non-negative');
        }

        $this->options['crypto_period_duration'] = $seconds;

        return $this;
    }

    /**
     * Set the group identifier for Widevine licenses (hex string).
     */
    public function withGroupId(string $hex): self
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            throw new InvalidArgumentException('Group ID must be a non-empty hex string');
        }

        $this->options['group_id'] = $hex;

        return $this;
    }

    /**
     * Enable encryption with a PlayReady key server.
     */
    public function withEnablePlayreadyEncryption(bool $enabled = true): self
    {
        if ($enabled) {
            $this->options['enable_playready_encryption'] = true;
        } else {
            $this->removeOption('enable_playready_encryption');
        }

        return $this;
    }

    /**
     * Set the PlayReady packaging server URL.
     */
    public function withPlayreadyServerUrl(string $url): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('PlayReady server URL must not be empty');
        }

        $this->options['playready_server_url'] = $url;

        return $this;
    }

    /**
     * Set the program identifier for the PlayReady packaging request.
     */
    public function withProgramIdentifier(string $identifier): self
    {
        if ($identifier === '') {
            throw new InvalidArgumentException('Program identifier must not be empty');
        }

        $this->options['program_identifier'] = $identifier;

        return $this;
    }

    /**
     * Set the absolute path to the CA certificate file (PEM format).
     */
    public function withCaFile(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('CA file path must not be empty');
        }

        $this->options['ca_file'] = $path;

        return $this;
    }

    /**
     * Set the absolute path to the client certificate file.
     */
    public function withClientCertFile(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Client cert file path must not be empty');
        }

        $this->options['client_cert_file'] = $path;

        return $this;
    }

    /**
     * Set the absolute path to the client certificate private key file.
     */
    public function withClientCertPrivateKeyFile(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Client cert private key file path must not be empty');
        }

        $this->options['client_cert_private_key_file'] = $path;

        return $this;
    }

    /**
     * Set the password for the client certificate private key file.
     */
    public function withClientCertPrivateKeyPassword(string $password): self
    {
        $this->options['client_cert_private_key_password'] = $password;

        return $this;
    }

    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function removeOption(string $key): self
    {
        unset($this->options[$key]);

        return $this;
    }

    public function build(): string
    {
        $parts = Collection::make();

        // Add stream definitions
        $this->streams->each(function (array $stream) use ($parts) {
            $streamParts = Collection::make($stream)
                ->map(function ($value, $key) {
                    $escapedKey = $this->escapeKey($key);
                    $sanitizedValue = $this->sanitizeDescriptorValue($value);

                    return sprintf('%s=%s', $escapedKey, $sanitizedValue);
                })
                ->values();

            $parts->push($streamParts->implode(','));
        });

        $this->validateCrossConstraints();

        // Add global options - early return if empty
        if (empty($this->options)) {
            return $parts->implode(' ');
        }

        Collection::make($this->options)->each(function ($value, $key) use ($parts) {
            $escapedKey = $this->escapeKey($key);

            if (is_bool($value)) {
                if ($value) {
                    $parts->push("--{$escapedKey}");
                }
            } elseif ($value !== null && $value !== '') {
                $parts->push(sprintf(
                    '--%s=%s',
                    $escapedKey,
                    $this->escapeValue($value)
                ));
            }
        });

        return $parts->implode(' ');
    }

    public function buildArray(): array
    {
        $arguments = [];

        // Add stream definitions
        $this->streams->each(function (array $stream) use (&$arguments) {
            $streamParts = Collection::make($stream)
                ->map(function ($value, $key) {
                    $escapedKey = $this->escapeKey($key);
                    $sanitizedValue = $this->sanitizeDescriptorValue($value);

                    return sprintf('%s=%s', $escapedKey, $sanitizedValue);
                })
                ->values();

            $arguments[] = $streamParts->implode(',');
        });

        $this->validateCrossConstraints();

        // Add global options
        Collection::make($this->options)->each(function ($value, $key) use (&$arguments) {
            if (is_bool($value)) {
                if ($value) {
                    $arguments[] = "--{$key}";
                }
            } elseif ($value !== null && $value !== '') {
                $arguments[] = "--{$key}";
                $arguments[] = (string) $value;
            }
        });

        return $arguments;
    }

    /**
     * Validate cross-option constraints that can only be checked at build time
     * because the conflicting options may be set in any order.
     *
     * @throws InvalidArgumentException
     */
    protected function validateCrossConstraints(): void
    {
        $opts = $this->options;

        // Fragment duration must not exceed segment duration
        $fragmentDuration = $opts['fragment_duration'] ?? null;
        $segmentDuration = $opts['segment_duration'] ?? null;

        if ($fragmentDuration !== null && $segmentDuration !== null && $fragmentDuration > $segmentDuration) {
            throw new InvalidArgumentException(
                "Fragment duration ({$fragmentDuration}s) must not be larger than segment duration ({$segmentDuration}s)"
            );
        }

        // AES signing key requires AES signing IV
        if (isset($opts['aes_signing_key']) && ! isset($opts['aes_signing_iv'])) {
            throw new InvalidArgumentException(
                'aes_signing_iv is required when aes_signing_key is specified'
            );
        }

        // AES and RSA signing methods are mutually exclusive
        if (isset($opts['aes_signing_key']) && isset($opts['rsa_signing_key_path'])) {
            throw new InvalidArgumentException(
                'aes_signing_key and rsa_signing_key_path are mutually exclusive'
            );
        }
    }

    public function reset(): self
    {
        $this->streams = Collection::make();
        $this->options = [];

        return $this;
    }

    public function getStreams(): Collection
    {
        return $this->streams;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function escapeKey(string $key): string
    {
        // Keys should only contain alphanumeric characters, underscores, and hyphens
        // This prevents command injection while allowing all valid Shaka Packager options
        if (! preg_match('/^[a-z0-9_-]+$/i', $key)) {
            throw new InvalidArgumentException(
                "Invalid key format: {$key}. Keys must contain only alphanumeric characters, underscores, and hyphens."
            );
        }

        return $key;
    }

    /**
     * Sanitize descriptor values to avoid Shaka stream parser issues.
     * - Normalize smart quotes to ASCII
     * - Replace commas (Shaka field separators) with hyphens
     * - Trim surrounding quotes
     * - Prefix leading dashes with ./ to avoid option-like confusion
     */
    protected function sanitizeDescriptorValue(mixed $value): string
    {
        $v = (string) $value;

        // Normalize typographic quotes
        $v = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $v);

        // Commas separate fields in Shaka descriptors; avoid them in values
        $v = str_replace(',', '-', $v);

        // Remove surrounding quotes if present
        $v = trim($v, "\"'");

        // If value starts with a dash, prefix with ./ for safety
        if (str_starts_with($v, '-')) {
            $v = './'.$v;
        }

        return $v;
    }

    protected function escapeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // Return raw string; Shaka Packager parses stream descriptors itself and
        // quotes can break field detection (e.g., filenames with leading dashes/parentheses).
        return (string) $value;
    }
}

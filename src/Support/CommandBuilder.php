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
        $fragmentDuration = $this->options['fragment_duration'] ?? null;
        $segmentDuration = $this->options['segment_duration'] ?? null;

        if ($fragmentDuration !== null && $segmentDuration !== null && $fragmentDuration > $segmentDuration) {
            throw new InvalidArgumentException(
                "Fragment duration ({$fragmentDuration}s) must not be larger than segment duration ({$segmentDuration}s)"
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

<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

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

    public function addStream(array $stream): self
    {
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

    public function withEncryption(array $encryptionConfig): self
    {
        $this->options['enable_raw_key_encryption'] = true;

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

    public function build(): string
    {
        $parts = Collection::make();

        // Add stream definitions
        $this->streams->each(function (array $stream) use ($parts) {
            $streamParts = Collection::make($stream)
                ->map(fn ($value, $key) => sprintf(
                    '%s=%s',
                    $this->escapeKey($key),
                    $this->escapeValue($value)
                ))
                ->values();

            $parts->push($streamParts->implode(','));
        });

        // Add global options
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
                ->map(fn ($value, $key) => sprintf('%s=%s', $key, $value))
                ->values();

            $arguments[] = $streamParts->implode(',');
        });

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

    protected function escapeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // Use escapeshellarg for string values to handle special characters
        return escapeshellarg((string) $value);
    }
}

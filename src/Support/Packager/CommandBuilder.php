<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

use Illuminate\Support\Collection;

/**
 * Builder for constructing Shaka Packager command arguments
 */
class CommandBuilder
{
    protected Collection $streams;

    protected array $options = [];

    public function __construct()
    {
        $this->streams = new Collection;
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

    public function addVideoStream(string $input, string $output, ?array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'video',
            'output' => $output,
        ], $options));
    }

    public function addAudioStream(string $input, string $output, ?array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'audio',
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

    public function build(): string
    {
        $parts = [];

        // Add stream definitions
        foreach ($this->streams as $stream) {
            $streamParts = [];
            foreach ($stream as $key => $value) {
                $streamParts[] = "{$key}={$value}";
            }
            $parts[] = implode(',', $streamParts);
        }

        // Add global options
        foreach ($this->options as $key => $value) {
            if (is_bool($value)) {
                $parts[] = "--{$key}";
            } else {
                $parts[] = "--{$key}={$value}";
            }
        }

        return implode(' ', $parts);
    }

    public function getStreams(): Collection
    {
        return $this->streams;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Media\Filesystem\TemporaryDirectories;
use Foxws\Media\Filesystem\TemporaryDirectory;
use Foxws\Media\Filters\Number;
use Foxws\Media\Packaging\Encryption;
use Foxws\Media\Packaging\Packager;
use Foxws\Media\Packaging\PackagingSpec;
use Foxws\Media\Packaging\PackagingStream;
use Foxws\Media\Process\Runner;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Packages with Shaka Packager: https://shaka-project.github.io/shaka-packager/
 */
class ShakaPackager implements Packager
{
    /**
     * @throws InvalidArgumentException
     */
    public function package(PackagingSpec $spec, TemporaryDirectory $directory, ?int $timeout = null): void
    {
        $inputs = app(TemporaryDirectories::class)->createCache();

        try {
            app(Runner::class)->run(
                ShakaExecutable::Packager,
                $this->arguments($spec, $directory->path(), $inputs),
                $timeout ?? Config::integer('shaka.timeout', 14400),
            );
        } finally {
            $inputs->delete();
        }
    }

    public function command(PackagingSpec $spec, string $directory): string
    {
        return app(Runner::class)->commandLine(ShakaExecutable::Packager, $this->arguments($spec, $directory));
    }

    /**
     * The stream descriptors followed by the global options. --quiet keeps Shaka Packager's info
     * logs off the error output, so only its warnings are logged.
     *
     * @param  TemporaryDirectory|null  $inputs  Where inputs with unsafe names are linked under a plain name; without it, inputs aren't fetched.
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    public function arguments(PackagingSpec $spec, string $directory, ?TemporaryDirectory $inputs = null): array
    {
        $directory = rtrim(str_replace('\\', '/', $directory), '/');

        $descriptors = array_map(
            fn (PackagingStream $stream, int $index): string => $this->descriptor($stream, $directory, $inputs, $index),
            $spec->streams,
            array_keys($spec->streams),
        );

        return [...$descriptors, ...$this->options($spec, $directory), '--quiet'];
    }

    protected function descriptor(PackagingStream $stream, string $directory, ?TemporaryDirectory $inputs, int $index): string
    {
        $fields = [
            'in' => $inputs !== null ? $this->input($stream, $inputs, $index) : $stream->media->path(),
            'stream' => $stream->type->value,
            'output' => "{$directory}/{$stream->output}",
            ...($stream->language !== null ? ['language' => $stream->language] : []),
            ...$stream->options,
        ];

        return implode(',', array_map(
            fn (string $key, string $value): string => $this->key($key).'='.$this->descriptorValue($value),
            array_keys($fields),
            $fields,
        ));
    }

    /**
     * The local input path. Commas separate descriptor fields and some characters confuse Shaka's
     * parser, so inputs with anything but plain characters are linked (or copied) under a plain name.
     */
    protected function input(PackagingStream $stream, TemporaryDirectory $inputs, int $index): string
    {
        $path = str_replace('\\', '/', $stream->media->localPath());

        if (preg_match('#^[A-Za-z0-9._/:-]+$#', $path) === 1) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $alias = $inputs->path("input-{$index}".($extension !== '' ? ".{$extension}" : ''));

        if (! file_exists($alias) && ! (function_exists('symlink') && @symlink($path, $alias))) {
            copy($path, $alias);
        }

        return $alias;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function options(PackagingSpec $spec, string $directory): array
    {
        if ($spec->segmentDuration !== null && $spec->fragmentDuration !== null && $spec->fragmentDuration > $spec->segmentDuration) {
            throw new InvalidArgumentException("The fragment duration ({$spec->fragmentDuration}s) can't be longer than the segment duration ({$spec->segmentDuration}s).");
        }

        $options = [
            'mpd_output' => $spec->dashManifest !== null ? "{$directory}/{$spec->dashManifest}" : null,
            'hls_master_playlist_output' => $spec->hlsPlaylist !== null ? "{$directory}/{$spec->hlsPlaylist}" : null,
            'hls_playlist_type' => $spec->hlsPlaylist !== null ? $spec->hlsPlaylistType?->value : null,
            'segment_duration' => $spec->segmentDuration !== null ? Number::format($spec->segmentDuration) : null,
            'fragment_duration' => $spec->fragmentDuration !== null ? Number::format($spec->fragmentDuration) : null,
            'default_language' => $spec->defaultLanguage,
            'default_text_language' => $spec->defaultTextLanguage,
            'allow_codec_switching' => $spec->allowCodecSwitching,
            'allow_approximate_segment_timeline' => $spec->approximateSegmentTimeline,
            ...$this->encryptionOptions($spec->encryption),
            ...$spec->options,
        ];

        $arguments = [];

        foreach ($options as $key => $value) {
            if ($value === true) {
                $arguments[] = '--'.$this->key($key);
            } elseif ($value !== null && $value !== false && $value !== '') {
                $arguments[] = '--'.$this->key($key).'='.$value;
            }
        }

        return $arguments;
    }

    /**
     * Raw key encryption options. Shaka Packager derives the keys of later crypto periods
     * from this one when rotating.
     *
     * @return array<string, string|bool|null>
     */
    protected function encryptionOptions(?Encryption $encryption): array
    {
        if ($encryption === null) {
            return [];
        }

        return [
            'enable_raw_key_encryption' => true,
            'keys' => sprintf('label=%s:key_id=%s:key=%s', $encryption->label ?? '', $encryption->key->keyId, $encryption->key->key),
            'protection_scheme' => $encryption->scheme?->value,
            'clear_lead' => Number::format($encryption->clearLead),
            'hls_key_uri' => $encryption->keyUri(),
            'crypto_period_duration' => $encryption->rotation !== null ? (string) $encryption->rotation : null,
        ];
    }

    /**
     * Only plain option and field names, so nothing can be injected through a key.
     *
     * @throws InvalidArgumentException
     */
    protected function key(string $key): string
    {
        if (preg_match('/^[a-z0-9_-]+$/i', $key) !== 1) {
            throw new InvalidArgumentException("Invalid Shaka Packager option or field name [{$key}].");
        }

        return $key;
    }

    /**
     * Commas separate descriptor fields, so they're replaced; a leading dash would look like an option.
     */
    protected function descriptorValue(string $value): string
    {
        $value = trim(str_replace(['’', '‘', '“', '”', ','], ["'", "'", '"', '"', '-'], $value), "\"'");

        return str_starts_with($value, '-') ? "./{$value}" : $value;
    }
}

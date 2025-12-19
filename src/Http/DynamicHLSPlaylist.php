<?php

declare(strict_types=1);

namespace Foxws\Shaka\Http;

use Foxws\Shaka\Filesystem\Disk;
use Foxws\Shaka\Filesystem\Media;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class DynamicHLSPlaylist implements Responsable
{
    protected ?Disk $disk = null;

    protected ?Media $media = null;

    /**
     * Callable to retrieve the URL for encryption keys.
     */
    protected $keyUrlResolver = null;

    /**
     * Callable to retrieve the URL for media files.
     */
    protected $mediaUrlResolver = null;

    /**
     * Callable to retrieve the URL for playlist files.
     */
    protected $playlistUrlResolver = null;

    /**
     * Cache for resolved key URLs.
     */
    protected array $keyCache = [];

    /**
     * Cache for resolved media URLs.
     */
    protected array $mediaCache = [];

    /**
     * Cache for resolved playlist URLs.
     */
    protected array $playlistCache = [];

    /**
     * Uses the 'filesystems.default' disk as default.
     */
    public function __construct(?string $disk = null)
    {
        $this->fromDisk($disk ?: config('filesystems.default'));
    }

    /**
     * Set the disk to open files from.
     */
    public function fromDisk($disk): self
    {
        $this->disk = Disk::make($disk);

        return $this;
    }

    /**
     * Instantiates a Media object for the given path and clears the cache.
     */
    public function open(string $path): self
    {
        $this->media = Media::make($this->disk, $path);

        $this->keyCache = [];
        $this->playlistCache = [];
        $this->mediaCache = [];

        return $this;
    }

    /**
     * Set the key URL resolver.
     */
    public function setKeyUrlResolver(callable $resolver): self
    {
        $this->keyUrlResolver = $resolver;
        $this->keyCache = [];

        return $this;
    }

    /**
     * Set the media URL resolver.
     */
    public function setMediaUrlResolver(callable $resolver): self
    {
        $this->mediaUrlResolver = $resolver;
        $this->mediaCache = [];

        return $this;
    }

    /**
     * Set the playlist URL resolver.
     */
    public function setPlaylistUrlResolver(callable $resolver): self
    {
        $this->playlistUrlResolver = $resolver;
        $this->playlistCache = [];

        return $this;
    }

    /**
     * Get the key URL resolver.
     */
    public function getKeyUrlResolver(): ?callable
    {
        return $this->keyUrlResolver;
    }

    /**
     * Get the media URL resolver.
     */
    public function getMediaUrlResolver(): ?callable
    {
        return $this->mediaUrlResolver;
    }

    /**
     * Get the playlist URL resolver.
     */
    public function getPlaylistUrlResolver(): ?callable
    {
        return $this->playlistUrlResolver;
    }

    /**
     * Resolve a key URL using the configured resolver.
     */
    protected function resolveKeyUrl(string $key): string
    {
        if (array_key_exists($key, $this->keyCache)) {
            return $this->keyCache[$key];
        }

        return $this->keyCache[$key] = call_user_func($this->keyUrlResolver, $key);
    }

    /**
     * Resolve a media URL using the configured resolver.
     */
    protected function resolveMediaUrl(string $filename): string
    {
        if (array_key_exists($filename, $this->mediaCache)) {
            return $this->mediaCache[$filename];
        }

        return $this->mediaCache[$filename] = call_user_func($this->mediaUrlResolver, $filename);
    }

    /**
     * Resolve a playlist URL using the configured resolver.
     */
    protected function resolvePlaylistUrl(string $filename): string
    {
        if (array_key_exists($filename, $this->playlistCache)) {
            return $this->playlistCache[$filename];
        }

        return $this->playlistCache[$filename] = call_user_func($this->playlistUrlResolver, $filename);
    }

    /**
     * Parses the lines into a Collection.
     */
    public static function parseLines(string $lines): Collection
    {
        return Collection::make(preg_split('/\n|\r\n?/', $lines));
    }

    /**
     * Returns a boolean whether the line contains a .M3U8 playlist filename,
     * a .TS segment filename, or a media filename (.mp4, .m4s, .m4a, .m4v, .aac, .vtt).
     */
    private static function lineHasMediaFilename(string $line): bool
    {
        return ! Str::startsWith($line, '#') && Str::endsWith($line, [
            '.m3u8',  // Playlist files
            '.ts',    // Transport stream segments
            '.mp4',   // MP4 media files
            '.m4s',   // fMP4 media segments
            '.m4a',   // Audio-only MP4
            '.m4v',   // Video-only MP4
            '.aac',   // AAC audio
            '.vtt',   // WebVTT subtitles
        ]);
    }

    /**
     * Returns the filename of the encryption key.
     */
    private static function extractKeyFromExtLine(string $line): ?string
    {
        preg_match_all('/#EXT-X-KEY:METHOD=AES-128,URI="([a-zA-Z0-9-_\/:]+.key)",IV=[a-z0-9]+/', $line, $matches);

        return $matches[1][0] ?? null;
    }

    /**
     * Extract playlist URI from EXT-X-MEDIA line.
     */
    private static function extractPlaylistFromExtMediaLine(string $line): ?string
    {
        if (! Str::startsWith($line, '#EXT-X-MEDIA:')) {
            return null;
        }

        preg_match('/URI="([^"]+)"/', $line, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Returns the processed content of the playlist.
     */
    public function get(): string
    {
        return $this->getProcessedPlaylist($this->media->getPath());
    }

    /**
     * Returns a collection of all processed segment playlists
     * and the processed main playlist.
     */
    public function all(): Collection
    {
        return static::parseLines(
            $this->disk->get($this->media->getPath())
        )->filter(function ($line) {
            return static::lineHasMediaFilename($line);
        })->mapWithKeys(function ($segmentPlaylist) {
            return [$segmentPlaylist => $this->getProcessedPlaylist($segmentPlaylist)];
        })->prepend(
            $this->getProcessedPlaylist($this->media->getPath()),
            $this->media->getPath()
        );
    }

    /**
     * Processes the given playlist.
     */
    public function getProcessedPlaylist(string $playlistPath): string
    {
        return static::parseLines($this->disk->get($playlistPath))->map(function (string $line) {
            if (static::lineHasMediaFilename($line)) {
                // Use playlist resolver for .m3u8 files, media resolver for everything else
                return Str::endsWith($line, '.m3u8')
                    ? $this->resolvePlaylistUrl($line)
                    : $this->resolveMediaUrl($line);
            }

            // Handle #EXT-X-KEY encryption lines
            $key = static::extractKeyFromExtLine($line);
            if ($key) {
                return str_replace(
                    '#EXT-X-KEY:METHOD=AES-128,URI="'.$key.'"',
                    '#EXT-X-KEY:METHOD=AES-128,URI="'.$this->resolveKeyUrl($key).'"',
                    $line
                );
            }

            // Handle #EXT-X-MEDIA lines with URI attribute
            $playlistUri = static::extractPlaylistFromExtMediaLine($line);
            if ($playlistUri) {
                return str_replace(
                    'URI="'.$playlistUri.'"',
                    'URI="'.$this->resolvePlaylistUrl($playlistUri).'"',
                    $line
                );
            }

            return $line;
        })->implode(PHP_EOL);
    }

    /**
     * Returns the playlist as an HTTP response.
     */
    public function toResponse($request)
    {
        return Response::make($this->get(), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
        ]);
    }
}

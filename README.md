# Laravel Shaka

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-shaka/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-shaka/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-shaka/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-shaka/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)

Runs [Shaka Packager](https://github.com/shaka-project/shaka-packager) from Laravel to turn video into HLS and DASH streams. Read the source from any Laravel disk, and write the result to any disk.

Shaka Packager packages already-encoded video; it doesn't re-encode. That makes it fast, but it can't create extra qualities. To encode and package in one step, see [Laravel Streamer](https://github.com/foxws/laravel-streamer).

See the [full documentation](docs): [Installation](docs/installation.md), [Usage](docs/usage.md), [URL Resolvers](docs/url-resolvers.md), [Queues](docs/queue-integration.md), [Encryption](docs/aes-encryption.md), [Configuration](docs/configuration.md), [Quick Reference](docs/quick-reference.md), [How It Works](docs/architecture.md), [Troubleshooting](docs/troubleshooting.md).

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- The [Shaka Packager](https://github.com/shaka-project/shaka-packager/releases) binary

## Installation

```bash
composer require foxws/laravel-shaka
```

```bash
php artisan vendor:publish --tag="shaka-config"
```

Install the `packager` binary, then check the setup:

```bash
php artisan shaka:info
```

See [Installation](docs/installation.md) for details.

## Quick start

```php
use Foxws\Shaka\Facades\Shaka;

$packager = Shaka::fromDisk('media')->open('videos/clip.mp4');

try {
    $packager
        ->addVideoStream('videos/clip.mp4', 'video.mp4')
        ->addAudioStream('videos/clip.mp4', 'audio.mp4')
        ->withMpdOutput('index.mpd')
        ->withHlsMasterPlaylist('master.m3u8')
        ->export()
        ->toDisk('s3')
        ->toPath('streams/clip/')
        ->save();
} finally {
    $packager->cleanupTemporaryFiles();
}
```

This writes one set of segments with both a DASH manifest and an HLS playlist, and uploads them to `streams/clip/` on the `s3` disk.

To serve a private stream, rewrite the playlist with signed URLs when it's requested:

```php
return Shaka::dynamicHLSPlaylist('s3')
    ->setMediaUrlResolver(fn (string $path) => Storage::disk('s3')->temporaryUrl("streams/clip/{$path}", now()->addHour()))
    ->open('streams/clip/master.m3u8')
    ->toResponse($request);
```

See [URL Resolvers](docs/url-resolvers.md) and [Encryption](docs/aes-encryption.md).

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Security policy](../../security/policy)
- [Laravel Streamer](https://github.com/foxws/laravel-streamer), for encoding and packaging in one step
- [Shaka Packager documentation](https://shaka-project.github.io/shaka-packager/html/)

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

This package started from ideas in [Laravel FFMpeg](https://github.com/protonemedia/laravel-ffmpeg) and [shaka-php](https://github.com/quasarstream/shaka-php).

Used by [Stry](https://github.com/francoism90/stry), a self-hosted video streaming app.

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).

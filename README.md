# Laravel Shaka Packager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-shaka/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/foxws/laravel-shaka/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-shaka/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/foxws/laravel-shaka/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)

A Laravel integration for Google's Shaka Packager, enabling you to create adaptive streaming content (DASH, HLS) with a fluent, Laravel-style API.

```php
use Foxws\Shaka\Facades\Shaka;

$result = Shaka::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('videos/input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6)
    ->export();
```

## Features

- 🎬 **Fluent API** - Laravel-style chainable methods
- 📁 **Multiple Disks** - Works with local, S3, and custom filesystems
- 🎯 **Adaptive Bitrate** - Create multi-quality streams easily
- 🔒 **Encryption & DRM** - Built-in support for content protection
- 📺 **DASH & HLS** - Support for both streaming protocols
- 🧪 **Testable** - Clean architecture with mockable components
- 📝 **Type-Safe** - Full PHP 8.1+ type declarations

## Documentation

📚 **[Full Documentation](docs/README.md)**

- [Quick Reference](docs/QUICK_REFERENCE.md) - Complete API reference
- [Architecture Overview](docs/ARCHITECTURE.md) - Understanding the design
- [Configuration](docs/CONFIGURATION.md) - Configuring the package

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x or higher
- Shaka Packager binary installed on your system

## Installation

Install the package via composer:

```bash
composer require foxws/laravel-shaka
```

Publish the config file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

Verify the installation:

```bash
php artisan shaka:verify
```

## Quick Start

### Basic Usage

```php
use Foxws\Shaka\Facades\Shaka;

$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export();
```

### Adaptive Bitrate Streaming

```php
$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addVideoStream('input.mp4', 'video_480p.mp4', ['bandwidth' => '1500000'])
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6)
    ->export();
```

### Working with Different Disks

```php
$result = Shaka::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export();
```

### HLS with Encryption

```php
$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.m3u8')
    ->addAudioStream('input.mp4', 'audio.m3u8')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withEncryption([
        'keys' => 'label=:key_id=abc:key=def',
        'key_server_url' => 'https://example.com/license',
    ])
    ->export();
```

### Dynamic URL Resolvers (HLS & DASH)

Customize how URLs are generated for your streaming manifests:

**HLS Example:**
```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;

$playlist = (new DynamicHLSPlaylist('videos'))
    ->setKeyUrlResolver(fn ($key) => route('video.key', ['key' => $key]))
    ->setMediaUrlResolver(fn ($file) => Storage::disk('cdn')->url($file))
    ->setPlaylistUrlResolver(fn ($pl) => route('video.playlist', ['playlist' => $pl]))
    ->open('master.m3u8');

return $playlist->toResponse($request);
```

**DASH Example:**
```php
use Foxws\Shaka\Http\DynamicDASHManifest;

$manifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(fn ($file) => Storage::disk('cdn')->url($file))
    ->setInitUrlResolver(fn ($file) => Storage::disk('cdn')->url("init/{$file}"))
    ->open('manifest.mpd');

return $manifest->toResponse($request);
```

**Use cases for URL resolvers:**
- 🔐 Generate signed URLs for secure content delivery
- 🌐 Integrate with CDN services
- 🏢 Support multi-tenant applications
- 🔄 Implement dynamic key rotation
- 📊 Track media access patterns

See [URL Resolver Examples](examples/UrlResolverExamples.php) and [Documentation](docs/URL_RESOLVERS.md) for more details.

## Available Methods

### Disk Management

- `fromDisk(string $disk)` - Set the disk to use
- `openFromDisk(string $disk, $paths)` - Set disk and open files in one call

### Dynamic URL Resolvers

**DynamicHLSPlaylist:**
- `new DynamicHLSPlaylist(?string $disk)` - Create HLS playlist processor
- `open(string $path)` - Open a playlist file
- `setKeyUrlResolver(callable $resolver)` - Set resolver for encryption key URLs
- `setMediaUrlResolver(callable $resolver)` - Set resolver for media segment URLs
- `setPlaylistUrlResolver(callable $resolver)` - Set resolver for sub-playlist URLs
- `get()` - Get processed playlist content
- `all()` - Get all processed playlists (master + segments)
- `toResponse($request)` - Return as HTTP response

**DynamicDASHManifest:**
- `new DynamicDASHManifest(?string $disk)` - Create DASH manifest processor
- `open(string $path)` - Open a manifest file
- `setMediaUrlResolver(callable $resolver)` - Set resolver for media segment URLs
- `setInitUrlResolver(callable $resolver)` - Set resolver for initialization segment URLs
- `get()` - Get processed manifest content
- `toResponse($request)` - Return as HTTP response

### Stream Configuration

- `addVideoStream(string $input, string $output, array $options = [])` - Add video stream
- `addAudioStream(string $input, string $output, array $options = [])` - Add audio stream
- `addStream(array $stream)` - Add custom stream

### Output Configuration

- `withMpdOutput(string $path)` - Set DASH manifest output
- `withHlsMasterPlaylist(string $path)` - Set HLS master playlist output
- `withSegmentDuration(int $seconds)` - Set segment duration
- `withEncryption(array $config)` - Enable encryption

### Execution

- `export()` - Execute the packaging operation

See the [Quick Reference](docs/QUICK_REFERENCE.md) for complete API documentation.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [francoism90](https://github.com/foxws)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

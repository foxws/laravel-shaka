# Laravel Shaka

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-shaka/actions/workflows/tests.yml/badge.svg)](https://github.com/foxws/laravel-shaka/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-shaka.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-shaka)

Package video and audio into HLS and DASH with [Shaka Packager](https://github.com/shaka-project/shaka-packager), on top of [foxws/laravel-media](https://github.com/foxws/laravel-media). Read the source from any Laravel disk, and write the result to any disk.

Shaka Packager packages already-encoded video; it doesn't re-encode. That makes it fast. Encode several qualities first with laravel-media's rendition ladder, then package them together.

See the [full documentation](docs): [Installation](docs/installation.md), [Usage](docs/usage.md), [Testing](docs/testing.md), [Configuration](docs/configuration.md), [Upgrading from 2.x](docs/upgrading.md).

## Requirements

- PHP 8.4 or higher
- Laravel 13
- [foxws/laravel-media](https://github.com/foxws/laravel-media) 0.3.4 or higher
- The [Shaka Packager](https://github.com/shaka-project/shaka-packager/releases) binary

## Installation

```bash
composer require foxws/laravel-shaka
```

Install the `packager` binary, then check the setup:

```bash
php artisan media:info
```

## Quick start

```php
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Facades\Media;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->shaka()
    ->addStreamsFrom()
    ->withHlsPlaylist()
    ->withDashManifest()
    ->withEncryption(scheme: ProtectionScheme::Cbcs)
    ->toDisk('s3')
    ->save('streams/clip');
```

This writes one set of encrypted segments with both an HLS playlist and a DASH manifest, and uploads them to `streams/clip` on the `s3` disk. `shaka()` is laravel-media's packaging builder with the Shaka Packager driver; set `MEDIA_PACKAGER=shaka` to use it for every `package()` call.

Shaka Packager's other options, such as DRM systems, key servers and live DASH, are typed methods on `ShakaOptions`:

```php
use Foxws\Shaka\ProtectionSystem;
use Foxws\Shaka\ShakaOptions;

->withOptions(ShakaOptions::make()->protectionSystems(ProtectionSystem::Widevine)->lowLatencyDashMode())
```

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Security policy](../../security/policy)
- [foxws/laravel-media](https://github.com/foxws/laravel-media)
- [Shaka Packager documentation](https://shaka-project.github.io/shaka-packager/html/)

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

Used by [Stry](https://github.com/francoism90/stry), a self-hosted video streaming app.

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).

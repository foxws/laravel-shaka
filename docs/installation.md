---
section: Getting Started
order: 1
---

# Installation

## Install the package

```bash
composer require foxws/laravel-shaka
```

This also installs [foxws/laravel-media](https://github.com/foxws/laravel-media), which opens the media, runs Shaka Packager and saves the result. Its own settings (temporary files, logging, disks) live in `config/media.php`.

Publish the config file to change the binary or the timeout:

```bash
php artisan vendor:publish --tag="shaka-config"
```

This creates `config/shaka.php`. See [Configuration](configuration.md).

## Install Shaka Packager

The package doesn't ship the binary. Download it from the [Shaka Packager releases](https://github.com/shaka-project/shaka-packager/releases) page, for example on Linux:

```bash
curl -fsSL -o /usr/local/bin/packager \
    https://github.com/shaka-project/shaka-packager/releases/latest/download/packager-linux-x64
chmod +x /usr/local/bin/packager
```

If the binary isn't on your `PATH` as `packager`, set its location:

```env
SHAKA_PACKAGER_BINARY=/opt/shaka/packager
```

## Package with Shaka by default

`shaka()` always packages with Shaka Packager. To use it for laravel-media's `package()`, `exportAsHLS()`, `exportAsDASH()` and `exportAsStreams()` too, make it the default driver:

```env
MEDIA_PACKAGER=shaka
```

## Check the setup

```bash
php artisan media:info
```

This lists `packager` next to ffmpeg and ffprobe, with the path and version it found. `php artisan about` shows the same paths.

## AI agents

The package includes a [Laravel Boost](https://github.com/laravel/boost) skill. Run `php artisan boost:install` (or `boost:update`) after installing, and your AI agent learns how to package, encrypt and test with it.

Next: [Usage](usage.md).

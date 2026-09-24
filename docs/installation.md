---
section: Getting Started
order: 1
---

# Installation

## Install the package

```bash
composer require foxws/laravel-shaka
```

Publish the config file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

This creates `config/laravel-shaka.php`. See [Configuration](configuration.md) for every option.

To upload to S3, also install the Flysystem S3 adapter if your app doesn't have it yet:

```bash
composer require league/flysystem-aws-s3-v3
```

## Install Shaka Packager

The package doesn't ship the binary. Download it from the [Shaka Packager releases](https://github.com/shaka-project/shaka-packager/releases) page, for example on Linux:

```bash
curl -fsSL -o /usr/local/bin/packager \
    https://github.com/shaka-project/shaka-packager/releases/latest/download/packager-linux-x64
chmod +x /usr/local/bin/packager
```

If the binary isn't on your `PATH` as `packager`, set its location:

```env
PACKAGER_PATH=/opt/shaka/packager
```

## Check the setup

```bash
php artisan shaka:info
```

This runs `packager --version` and shows the binary, its version, the timeout, the temporary directory and the log channel. It fails if the binary can't run or the temporary directory isn't writable. A temporary directory that doesn't exist yet is fine; it's created on first use.

## Laravel Boost

The package includes a [Laravel Boost](https://github.com/laravel/boost) skill. Run `php artisan boost:install` (or `boost:update`) after installing, and your AI agent learns how to package, encrypt and serve streams with it.

Next: [Usage](usage.md).

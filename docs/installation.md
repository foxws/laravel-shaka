---
section: Getting Started
order: 1
---

# Installation

## Requirements

- PHP 8.3 or higher
- Laravel 12.x or higher
- The Shaka Packager binary, installed on your system or available in a Docker container

## Install the package

```bash
composer require foxws/laravel-shaka
```

Publish the config file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Installing Shaka Packager

The package itself doesn't include Shaka Packager — you need the binary installed separately. Visit the [Shaka Packager releases](https://github.com/shaka-project/shaka-packager/releases) page for install instructions for your platform.

## Verify installation

Once everything is installed, check that it's set up correctly:

```bash
php artisan shaka:info
```

This command checks that:

- The binary exists and can be run
- The binary's version can be read
- The configuration is valid
- The temporary directory is accessible

Continue to [Usage](./usage.md) to start packaging media.

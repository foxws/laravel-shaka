---
sidebar_position: 2
---

# Installation

## Requirements

- PHP 8.3 or higher
- Laravel 12.x or higher
- Shaka Packager binary installed on your system or Docker container

## Install the package

```bash
composer require foxws/laravel-shaka
```

Publish the config file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Installing Shaka Packager

Install the Shaka Packager binary on your system. Visit the [Shaka Packager releases](https://github.com/shaka-project/shaka-packager/releases) page for installation instructions.

## Verify installation

After installation, verify that Shaka Packager is properly configured:

```bash
php artisan shaka:info
```

This will check:

- Binary exists and is executable
- Can retrieve version information
- Configuration is properly set up
- Temporary directory is accessible

Continue to [Usage](./usage.md) to start packaging media.

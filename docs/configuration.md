---
sidebar_position: 5
---

# Configuration

Laravel Shaka can be configured via the `config/laravel-shaka.php` file.

## Publishing configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Configuration options

### Packager binary

Configure the path to the Shaka Packager binary:

```php
'packager' => [
    'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
],
```

**Environment variable:**

```env
PACKAGER_PATH=/usr/local/bin/packager
```

**Multiple binary paths:**
The system will search for the first available binary:

```php
'packager' => [
    'binaries' => [
        '/usr/local/bin/packager',
        '/usr/bin/packager',
        '/opt/shaka-packager/packager',
    ],
],
```

### Timeout

Set the maximum execution time for packaging operations:

```php
'timeout' => 60 * 60 * 4, // 4 hours in seconds
```

**Environment variable:**

```env
PACKAGER_TIMEOUT=14400
```

**Considerations:**

- Longer videos require more time
- 4K content takes significantly longer than 1080p
- Multiple quality variants multiply processing time
- Consider your server's PHP `max_execution_time` setting

### Logging

Enable logging to track packaging operations:

```php
'log_channel' => env('PACKAGER_LOG_CHANNEL', false),
```

**Environment variables:**

```env
# Disable logging (default)
PACKAGER_LOG_CHANNEL=false

# Use default log channel
PACKAGER_LOG_CHANNEL=stack

# Use custom channel
PACKAGER_LOG_CHANNEL=packager
```

**Custom log channel:**
Define a custom channel in `config/logging.php`:

```php
'channels' => [
    'packager' => [
        'driver' => 'daily',
        'path' => storage_path('logs/packager.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Temporary files

Configure where temporary files are stored:

```php
'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),
```

**Environment variable:**

```env
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
```

**Considerations:**

- Remote files (S3, etc.) are copied here before processing
- Ensure sufficient disk space
- Clean up regularly with `cleanupTemporaryFiles()`
- Use `/dev/shm` for faster processing (RAM disk)

### Encrypted files

Configure location for encrypted temporary files:

```php
'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),
```

**Environment variable:**

```env
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
```

### Storage space guards

Fail fast with a clear exception instead of a job dying mid-packaging when a
storage-constrained root (e.g. a size-limited tmpfs) runs low on space. All
three checks are disabled by default (`0`), so upgrading does not change
behavior for existing installs - set them explicitly to opt in.

```php
'temporary_files_min_free' => env('PACKAGER_TEMPORARY_MIN_FREE', 0),
'temporary_files_size_multiplier' => env('PACKAGER_TEMPORARY_SIZE_MULTIPLIER', 1.5),
'cache_files_min_free' => env('PACKAGER_CACHE_MIN_FREE', 0),
```

**Environment variables:**

```env
PACKAGER_TEMPORARY_MIN_FREE=1073741824       # 1 GiB floor on temporary_files_root
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5       # safety factor applied to the job's input size
PACKAGER_CACHE_MIN_FREE=10485760             # 10 MiB floor on cache_files_root
```

**How the checks work:**

- `temporary_files_min_free` - a static floor checked against `temporary_files_root` before a job starts.
- `temporary_files_size_multiplier` - before packaging starts, the combined size of the job's source input files (`MediaCollection::totalSize()`) is multiplied by this and checked too, on top of the static floor. Packager repackages/segments already-encoded input rather than re-encoding it, so output size tracks input size closely - this catches jobs whose *own* footprint won't fit, not just a generically-nearly-full root.
- `cache_files_min_free` - a separate floor for `cache_files_root` (manifests/encryption keys). Kept independent from `temporary_files_min_free` because this root is often a much smaller mount than the main temporary root (see the tmpfs example below) - a multi-GB floor meant for the main root would permanently break a small cache mount.

Both failure modes throw `Foxws\Shaka\Exceptions\InsufficientStorageException`, catchable separately from other packaging failures (e.g. in a queued job's `failed()` method).

**Tuning the multiplier:** `1.5` is a starting point, not a measurement. After a real job runs, compare `du -sh` on its temporary directory against the combined size of its source input files, and adjust `PACKAGER_TEMPORARY_SIZE_MULTIPLIER` from there - especially if you generate separate HLS and DASH segment sets rather than sharing CMAF segments across both, which pushes real usage closer to 2x than 1.5x.

#### Example: temporary_files_root on a Podman tmpfs

If you run Horizon/queue workers in Podman and want packaging scratch space
to live in RAM instead of hitting your NVMe (segments are written once,
uploaded, then deleted - nothing here needs to survive a restart), mount
the root as a `tmpfs` in your `.container` quadlet instead of a regular
volume:

```ini
# horizon.container (podman quadlet)
[Container]
...
# Was: Volume=app-cache:/cache:rw,z
Tmpfs=/cache:rw,size=12g,mode=1777
```

Then point the package at it and set a floor sized to fit comfortably inside
that tmpfs, leaving headroom for concurrent jobs:

```env
PACKAGER_TEMPORARY_FILES_ROOT=/cache/temp/packager
PACKAGER_TEMPORARY_MIN_FREE=1073741824   # 1 GiB
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5
```

`cache_files_root` (manifests/keys) typically points at `/dev/shm`, a
separate tmpfs the container runtime mounts automatically. Keep its floor
small relative to that mount's actual size (often just tens of MB via a
container's `ShmSize`):

```env
PACKAGER_CACHE_FILES_ROOT=/dev/shm
PACKAGER_CACHE_MIN_FREE=10485760   # 10 MiB
```

> **A tmpfs `size=` is a quota, not a reservation** - it does not protect you
> from concurrent jobs collectively exceeding it. Pair this with a
> concurrency limit on your queue (e.g. Horizon's `maxProcesses`) sized so
> `workers x largest expected job footprint` stays comfortably under the
> tmpfs size, and treat `temporary_files_min_free` as a fail-fast safety net
> for the jobs that slip past that limit, not as the primary defense.

## Complete configuration example

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shaka Packager Binary
    |--------------------------------------------------------------------------
    |
    | Path to the Shaka Packager binary. Can be a string or array of paths.
    | The system will use the first available binary.
    |
    */

    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for packaging operations.
    | Adjust based on your content size and quality requirements.
    |
    */

    'timeout' => env('PACKAGER_TIMEOUT', 60 * 60 * 4), // 4 hours

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log channel for packaging operations. Set to false to disable logging.
    | Use your default log channel or define a custom one.
    |
    */

    'log_channel' => env('PACKAGER_LOG_CHANNEL', false),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files during packaging operations.
    | Remote files are downloaded here before processing.
    |
    */

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Temporary Files
    |--------------------------------------------------------------------------
    |
    | Directory for encrypted temporary files. Using /dev/shm (RAM disk)
    | provides better performance for encryption operations.
    |
    */

    'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),

    /*
    |--------------------------------------------------------------------------
    | Storage Space Guards
    |--------------------------------------------------------------------------
    |
    | Fail fast with a clear exception instead of a job dying mid-packaging
    | when a storage-constrained root runs low on space. Set to 0 to
    | disable a given check.
    |
    */

    'temporary_files_min_free' => env('PACKAGER_TEMPORARY_MIN_FREE', 0),
    'temporary_files_size_multiplier' => env('PACKAGER_TEMPORARY_SIZE_MULTIPLIER', 1.5),
    'cache_files_min_free' => env('PACKAGER_CACHE_MIN_FREE', 0),

];
```

## Environment configuration

Example `.env` configuration:

```env
# Shaka Packager Configuration
PACKAGER_PATH=/usr/local/bin/packager
PACKAGER_TIMEOUT=14400
PACKAGER_LOG_CHANNEL=packager
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
PACKAGER_TEMPORARY_MIN_FREE=1073741824
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5
PACKAGER_CACHE_MIN_FREE=10485760
```

## Verification

After configuration, verify your setup:

```bash
php artisan shaka:info
```

This command checks:

- Binary path is valid and executable
- Can retrieve version information
- Timeout is configured
- Logger is properly set up

## Runtime configuration

You can also configure the packager at runtime:

```php
use Foxws\Shaka\Support\Packager\Packager;
use Foxws\Shaka\Support\Packager\ShakaPackager;

// Create with custom configuration
$driver = new ShakaPackager(
    binaryPath: '/custom/path/packager',
    logger: Log::channel('custom'),
    timeout: 7200
);

$packager = new Packager($driver, Log::channel('custom'));
```

Or using the static create method:

```php
$packager = Packager::create(
    logger: Log::channel('packager'),
    configuration: [
        'packager' => ['binaries' => '/custom/path/packager'],
        'timeout' => 7200,
    ]
);
```

## Driver configuration

Modify driver settings after instantiation:

```php
$driver = app(ShakaPackager::class);

// Change timeout
$driver->setTimeout(7200);

// Change logger
$driver->setLogger(Log::channel('debug'));
```

## Troubleshooting

### Binary not found

If you see "Executable not found" errors:

1. Verify the binary exists: `which packager`
2. Check permissions: `ls -l /usr/local/bin/packager`
3. Ensure it's executable: `chmod +x /usr/local/bin/packager`
4. Update config with correct path

### Timeout errors

If operations timeout:

1. Increase timeout in config
2. Check server PHP `max_execution_time`
3. Consider queueing long operations
4. Optimize video settings (resolution, bitrate)

### Permission errors

If you see permission errors:

1. Check temporary directory permissions
2. Ensure web server user can write
3. Verify binary is executable
4. Check SELinux/AppArmor policies

### Logging issues

If logging doesn't work:

1. Verify log channel exists in `config/logging.php`
2. Check log directory permissions
3. Ensure channel is properly configured
4. Test with a simple log entry

See the [Troubleshooting](./troubleshooting.md) guide for more issues and solutions.

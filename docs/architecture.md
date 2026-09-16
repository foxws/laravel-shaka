---
section: Reference
order: 3
---

# Architecture Overview

Laravel Shaka is built as a set of clean, testable layers, following the same pattern used by PHP-FFmpeg and Laravel FFmpeg.

## Architecture layers

### 1. Driver layer (`ShakaPackager`)

This layer talks directly to the Shaka Packager binary:

```php
namespace Foxws\Shaka\Support\Packager;

class ShakaPackager
{
    protected string $binaryPath;
    protected ?LoggerInterface $logger;
    protected int $timeout;

    // Binary execution
    public function command(string $command): string;

    // Version detection
    public function getVersion(): string;

    // Configuration
    public function setTimeout(int $timeout): self;
}
```

**What it's responsible for:**

- Finding and validating the binary path
- Running commands, with timeout handling
- Process management via Laravel's `Process` facade
- Checking the binary's version
- Error handling and exceptions
- Logger integration

**Why it's split out:**

- Keeps binary execution separate from business logic
- Easy to mock in tests
- Error handling stays consistent
- Logging is centralized

### 2. Business logic layer (`Packager`)

This layer is the high-level API you actually call:

```php
namespace Foxws\Shaka\Support\Packager;

class Packager
{
    protected ShakaPackager $driver;
    protected ?MediaCollection $mediaCollection;
    protected ?CommandBuilder $builder;

    // Media management
    public function open(MediaCollection $mediaCollection): self;

    // Stream configuration
    public function addVideoStream(string $input, string $output, array $options = []): self;
    public function addAudioStream(string $input, string $output, array $options = []): self;

    // Output configuration
    public function withMpdOutput(string $path): self;
    public function withHlsMasterPlaylist(string $path): self;

    // Execution
    public function export(): PackagerResult;
}
```

**What it's responsible for:**

- Managing media collections
- Building commands via `CommandBuilder`
- Translating the high-level API into binary commands
- Logging packaging operations
- Returning structured results

**Why it's split out:**

- Gives you a fluent, chainable API
- Keeps business logic separate from binary execution
- Type-safe operations throughout
- Returns structured result objects instead of raw output

### 3. Facade layer (`Shaka` & `MediaOpenerFactory`)

This layer provides the Laravel-style interface you interact with day to day:

```php
namespace Foxws\Shaka;

class Shaka
{
    protected ?Disk $disk;
    protected ?Packager $packager;
    protected ?MediaCollection $collection;

    // Disk management
    public function fromDisk(Filesystem|string $disk): self;
    public function openFromDisk(Filesystem|string $disk, $paths): self;

    // Media management
    public function open($paths): self;

    // Forwards all Packager methods
    public function __call($method, $arguments);
}
```

**What it's responsible for:**

- Managing filesystem disks
- Opening media files
- Forwarding calls through to `Packager`
- Providing convenient helper methods

**Why it's split out:**

- A clean, intuitive entry point
- Follows Laravel conventions
- Supports multiple disks
- Supports method chaining

## Component relationships

```
┌─────────────────────────────────────────────┐
│           Shaka (Facade)                    │
│  - Disk management                          │
│  - Media file opening                       │
│  - Method forwarding                        │
└────────────────┬────────────────────────────┘
                 │
                 ├──> MediaCollection (Media files)
                 │
                 v
┌─────────────────────────────────────────────┐
│           Packager (Business Logic)         │
│  - Stream configuration                     │
│  - Command building                         │
│  - Fluent API                              │
└────────────────┬────────────────────────────┘
                 │
                 ├──> CommandBuilder (Command construction)
                 ├──> Stream (Stream objects)
                 │
                 v
┌─────────────────────────────────────────────┐
│      ShakaPackager (Binary)           │
│  - Binary execution                         │
│  - Process management                       │
│  - Error handling                           │
└────────────────┬────────────────────────────┘
                 │
                 v
        [Shaka Packager Binary]
```

## Supporting classes

### CommandBuilder

Builds packager command strings, fluently:

```php
$builder = CommandBuilder::make()
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6);

$command = $builder->build();
```

### Stream

Represents a single, immutable stream configuration. `setOutput()`, `setOptions()`, and `addOption()` each return a new instance rather than changing the current one:

```php
$stream = Stream::video($media)
    ->setOutput('video.mp4')
    ->addOption('bandwidth', '5000000');

$commandString = $stream->toCommandString();
// "in=/path/to/input.mp4,stream=video,output=video.mp4,bandwidth=5000000"
```

### PackagerResult

The structured result returned from a packaging operation:

```php
$result = $packager->export();

$output = $result->getOutput();
$result->toDisk('s3'); // Copy the temp output to a target disk

$result->hasCopyFailures();
$result->getFailedFiles();      // array<int, CopyFailure>
$result->getEncryptionKeys();   // array<int, EncryptionKeyFile>
```

### Media & MediaCollection

Represents your input media files:

```php
$media = Media::make($disk, 'video.mp4');
$collection = MediaCollection::make([$media]);

$localPath = $media->getLocalPath();
$filename = $media->getFilename();
```

## Service provider registration

The package uses Laravel's service container for dependency injection:

```php
// ShakaServiceProvider.php

// Register driver
$this->app->singleton(ShakaPackager::class, function ($app) {
    $logger = $app->make('laravel-shaka-logger');
    $config = $app->make('laravel-shaka-configuration');

    return ShakaPackager::create($logger, $config);
});

// Register packager (scoped, not singleton: it holds per-export state like
// the CommandBuilder and temp directory, which must not leak across requests
// under Octane)
$this->app->scoped(Packager::class, function ($app) {
    $driver = $app->make(ShakaPackager::class);
    $logger = $app->make('laravel-shaka-logger');

    return new Packager($driver, $logger);
});
```

## Error handling

The package uses a clear exception hierarchy. One thing worth knowing: `ExecutableNotFoundException` exists, but nothing currently throws it. A missing or non-executable binary instead surfaces as a `RuntimeException` from the underlying `Process` call, the first time the packager binary is actually invoked:

```php
try {
    $result = Shaka::open('input.mp4')->export();
} catch (RuntimeException $e) {
    // Command execution failed (including: binary not found/not executable)
} catch (InvalidArgumentException $e) {
    // Invalid input
}
```

## Testing strategy

This layered design makes testing straightforward:

```php
// Mock the driver
$driver = Mockery::mock(ShakaPackager::class);
$driver->shouldReceive('command')->andReturn('success');

$packager = new Packager($driver);
$result = $packager->open($collection)->export();
```

## Extension points

### Custom drivers

Extend the driver for custom behavior:

```php
class CustomPackagerDriver extends ShakaPackager
{
    public function customOperation(array $options): string
    {
        $command = $this->buildCustomCommand($options);
        return $this->command($command);
    }
}
```

### Custom streams

`Stream`'s constructor is `protected`, not `private`, specifically so it can be subclassed. Its mutator methods use `new static(...)` so a subclass instance survives `with*()` calls. Give your subclass its own named constructor rather than overriding `make()` — its signature (`Media $media, string $type = 'video'`) won't accept an incompatible override:

```php
class SubtitleStream extends Stream
{
    public static function subtitle(Media $media): self
    {
        return new self($media, null, 'text');
    }
}
```

### Custom results

Extend the result objects:

```php
class DetailedPackagerResult extends PackagerResult
{
    public function getKeyCount(): int
    {
        return count($this->getEncryptionKeys());
    }
}
```

## Best practices

1. **Use dependency injection** - Get `Packager` from the container rather than constructing it yourself.
2. **Use the facade for simple operations** - `Shaka::open()` covers most day-to-day tasks.
3. **Only reach for the driver directly when you need low-level control.**
4. **Enable logging in production** - It makes packaging operations easy to trace.
5. **Set timeouts that match your content** - Larger files need more time.
6. **Handle exceptions by type** - Different errors call for different handling.
7. **Run the verification command during deployment** - `php artisan shaka:info`.

## Performance considerations

- **Long-running operations** - Set the timeout based on your content.
- **Memory usage** - Large files may need more memory.
- **Parallel processing** - Consider a queue for handling multiple files.
- **Temporary files** - Clean up with `cleanupTemporaryFiles()`.
- **Remote disks** - Files are copied locally before processing starts.

## Security considerations

- **Binary path validation** - The driver validates that the binary exists.
- **Input sanitization** - Use proper escaping for file paths.
- **Encryption** - Use `withAESEncryption()` for DRM content, see [AES Encryption](./aes-encryption.md).
- **Access control** - Check user permissions before processing.
- **Temporary files** - Make sure they're cleaned up and permissioned correctly.

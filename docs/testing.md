---
section: Usage
order: 2
---

# Testing

Don't run Shaka Packager in tests. Fake laravel-media with `Media::fake()`, and let `FakeShaka` answer Shaka Packager:

```php
use Foxws\Media\Facades\Media;
use Foxws\Shaka\ShakaExecutable;
use Foxws\Shaka\Testing\FakeShaka;
use Illuminate\Support\Facades\Storage;

it('packages uploads into streams', function () {
    Storage::fake('media');

    $fake = FakeShaka::respond(Media::fake());

    PackageVideo::dispatchSync($video);

    $fake->assertRan(ShakaExecutable::Packager, fn (array $arguments) => in_array('--protection_scheme=cbcs', $arguments, true));
    Storage::disk('media')->assertExists("streams/{$video->id}/master.m3u8");
});
```

`FakeShaka` writes a placeholder file for every stream output and manifest, so `save()` uploads them to the target disk like a real run.

To test failures, make the next run fail:

```php
$fake->failNext(ShakaExecutable::Packager, 'Packaging Error: 5 (FILE_FAILURE)');
```

laravel-media's other assertions work too, such as `assertNotRan()`, `assertRanTimes()` and `assertSaved()`. `command()` on the builder returns the command line without running it.

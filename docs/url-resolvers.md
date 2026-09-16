---
section: Usage
order: 2
---

# Dynamic URL Resolvers

URL resolvers let you control how URLs are generated for your streaming content, instead of the raw paths inside a playlist or manifest being used as-is. This is inspired by Laravel FFMpeg, and the package ships two classes for it: one for HLS, one for DASH.

## Overview

When serving adaptive streaming content, different pieces of the playlist or manifest need their own URLs:

**HLS:**

| Piece | What it is |
| --- | --- |
| Encryption keys | DRM keys for encrypted segments |
| Media segments | `.ts` video/audio chunks |
| Playlists | `.m3u8` playlist files |

**DASH:**

| Piece | What it is |
| --- | --- |
| Media segments | Video/audio segments |
| Initialization segments | Init segment for each representation |

## Classes

### DynamicHLSPlaylist

Processes and rewrites HLS playlists (`.m3u8` files).

```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;

$playlist = new DynamicHLSPlaylist('disk-name');
```

### DynamicDASHManifest

Processes and rewrites DASH manifests (`.mpd` files).

```php
use Foxws\Shaka\Http\DynamicDASHManifest;

$manifest = new DynamicDASHManifest('disk-name');
```

## HLS usage

### Basic example

```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

$playlist = (new DynamicHLSPlaylist('videos'))
    ->setKeyUrlResolver(function ($key) {
        return route('video.key', ['key' => $key]);
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url($filename);
    })
    ->setPlaylistUrlResolver(function ($playlist) {
        return route('video.playlist', ['playlist' => $playlist]);
    })
    ->open('master.m3u8');

// Get the processed content
$content = $playlist->get();

// Or return it as an HTTP response
return $playlist->toResponse($request);
```

### HLS methods

| Method | What it does |
| --- | --- |
| `setKeyUrlResolver(callable $resolver)` | Sets the resolver for encryption key URLs, used in `#EXT-X-KEY` tags. |
| `setMediaUrlResolver(callable $resolver)` | Sets the resolver for media segment URLs (`.ts` files). |
| `setPlaylistUrlResolver(callable $resolver)` | Sets the resolver for sub-playlist URLs (`.m3u8` files). |
| `get(): string` | Returns the processed playlist content as a string. |
| `all(): Collection` | Returns a collection of every processed playlist (master + variants). |
| `toResponse($request)` | Returns an HTTP response with the correct content type (`application/vnd.apple.mpegurl`). |

A resolver is just a callback that receives a filename and returns a URL, for example:

```php
$playlist->setKeyUrlResolver(function (string $key) {
    return "https://keys.example.com/{$key}";
});

$playlist->setMediaUrlResolver(function (string $filename) {
    return "https://cdn.example.com/segments/{$filename}";
});
```

## DASH usage

### Basic example

```php
use Foxws\Shaka\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

$manifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url("segments/{$filename}");
    })
    ->setInitUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url("init/{$filename}");
    })
    ->open('manifest.mpd');

// Get the processed content
$content = $manifest->get();

// Or return it as an HTTP response
return $manifest->toResponse($request);
```

### DASH methods

| Method | What it does |
| --- | --- |
| `setMediaUrlResolver(callable $resolver)` | Sets the resolver for media segment URLs and `BaseURL` elements. |
| `setInitUrlResolver(callable $resolver)` | Sets the resolver for initialization segment URLs. |
| `get(): string` | Returns the processed manifest content as a string. |
| `toResponse($request)` | Returns an HTTP response with the correct content type (`application/dash+xml`). |

## Performance

Both classes cache resolved URLs automatically. Each unique filename is only resolved once per instance, so calling a resolver twice for the same file costs nothing extra:

```php
// First call - the resolver runs
$playlist->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

// Later calls for the same file reuse the cached result
```

Setting a new resolver clears the cache automatically.

## Use cases

### 1. CDN integration

```php
$playlist = (new DynamicHLSPlaylist('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return config('services.cdn.url')."/{$filename}";
    })
    ->open('master.m3u8');
```

### 2. Signed URLs for security

```php
$playlist = (new DynamicHLSPlaylist('private'))
    ->setKeyUrlResolver(function ($key) {
        return Storage::disk('s3')->temporaryUrl("keys/{$key}", now()->addHour());
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('s3')->temporaryUrl("segments/{$filename}", now()->addHours(2));
    })
    ->open('master.m3u8');
```

See [AES Encryption](./aes-encryption.md) for how this pairs with encrypted content.

### 3. Multi-tenant applications

```php
$tenantId = auth()->user()->tenant_id;

$playlist = (new DynamicHLSPlaylist('tenants'))
    ->setMediaUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.media', ['tenant' => $tenantId, 'file' => $filename]);
    })
    ->open("tenant-{$tenantId}/master.m3u8");
```

### 4. Controller integration

```php
namespace App\Http\Controllers;

use App\Models\Video;
use Foxws\Shaka\Http\DynamicHLSPlaylist;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function playlist(Request $request, Video $video)
    {
        $this->authorize('view', $video);

        $playlist = (new DynamicHLSPlaylist('videos'))
            ->setKeyUrlResolver(fn ($key) => route('video.key', ['video' => $video->id, 'key' => $key]))
            ->setMediaUrlResolver(fn ($file) => Storage::disk('cdn')->url("videos/{$video->id}/{$file}"))
            ->setPlaylistUrlResolver(fn ($pl) => route('video.playlist', ['video' => $video->id, 'playlist' => $pl]))
            ->open($video->hls_path);

        return $playlist->toResponse($request);
    }

    public function key(Video $video, string $key)
    {
        $this->authorize('view', $video);

        return Storage::disk('private')->download("videos/{$video->id}/keys/{$key}");
    }
}
```

### 5. DASH with multiple CDNs

```php
$manifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        // Route to different CDNs based on file type
        if (str_contains($filename, 'video')) {
            return "https://video-cdn.example.com/{$filename}";
        }
        return "https://audio-cdn.example.com/{$filename}";
    })
    ->open('manifest.mpd');
```

## Comparison with Laravel FFMpeg

This implementation follows the same pattern as Laravel FFMpeg's dynamic playlist classes:

**Laravel FFMpeg:**
```php
$playlist = (new DynamicHLSPlaylist('videos'))
    ->open('master.m3u8')
    ->setMediaUrlResolver(fn ($file) => route('media', ['file' => $file]))
    ->setKeyUrlResolver(fn ($key) => route('key', ['key' => $key]));

return $playlist->toResponse($request);
```

**Laravel Shaka (this package):**
```php
$playlist = (new DynamicHLSPlaylist('videos'))
    ->open('master.m3u8')
    ->setMediaUrlResolver(fn ($file) => route('media', ['file' => $file]))
    ->setKeyUrlResolver(fn ($key) => route('key', ['key' => $key]));

return $playlist->toResponse($request);
```

The API is intentionally the same shape. On top of it, this package also provides `DynamicDASHManifest` for DASH content.

## Best practices

1. **Use Laravel helpers** - Reach for `route()`, `url()`, and `Storage::url()` so URLs stay consistent with the rest of your app.
2. **Check authorization** - Always verify the user can view the media before serving a resolver-built URL.
3. **Sign URLs for sensitive content** - Use `temporaryUrl()` for time-limited access.
4. **Handle resolver failures** - Think through what should happen if a resolver throws or returns nothing.
5. **Test your resolvers** - Unit test the URL-generation logic on its own.
6. **Let caching do its job** - URL resolution is already cached per instance, so you don't need to add your own layer.

## Examples

For more complete examples, see [UrlResolverExamples.php](https://github.com/foxws/laravel-shaka/blob/main/examples/UrlResolverExamples.php) in the repository.

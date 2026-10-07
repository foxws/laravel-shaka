<?php

declare(strict_types=1);

namespace Foxws\Shaka\Testing;

use Foxws\Media\Testing\MediaFake;
use Foxws\Shaka\ShakaExecutable;
use Illuminate\Filesystem\Filesystem;

/**
 * Answers Shaka Packager in Media::fake(): every stream output and manifest is written as a
 * placeholder file, so the export saves them to the target disk.
 */
final class FakeShaka
{
    public static function respond(MediaFake $fake): MediaFake
    {
        return $fake->respondUsing(ShakaExecutable::Packager, function (array $arguments): string {
            foreach ($arguments as $argument) {
                if (preg_match('/(?:^|,)output=([^,]+)/', $argument, $matches) === 1
                    || preg_match('/^--(?:mpd_output|hls_master_playlist_output)=(.+)$/', $argument, $matches) === 1) {
                    new Filesystem()->ensureDirectoryExists(dirname($matches[1]));
                    file_put_contents($matches[1], 'fake media');
                }
            }

            return '';
        });
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Support\Filesystem\Disk;
use Foxws\Shaka\Support\Filesystem\Media;
use Foxws\Shaka\Support\Filesystem\MediaCollection;
use Foxws\Shaka\Support\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager\Packager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class Shaka
{
    //
}

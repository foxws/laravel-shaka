<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Media\Executables\Binary;
use Illuminate\Support\Facades\Config;

enum ShakaExecutable: string implements Binary
{
    case Packager = 'packager';

    public function identifier(): string
    {
        return $this->value;
    }

    public function configuredPath(): string
    {
        $configured = Config::get('shaka.binary');

        return is_string($configured) && $configured !== '' ? $configured : $this->value;
    }

    public function environmentKey(): string
    {
        return 'SHAKA_PACKAGER_BINARY';
    }

    public function versionArguments(): array
    {
        return ['--version'];
    }
}

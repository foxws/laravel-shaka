<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

class ShakaPackager
{
    public function __construct(
        public readonly ?string $binaryPath = null,
        public readonly ?string $logChannel = null,
        public readonly ?int $timeout = null,
        public readonly ?string $temporaryFilesRoot = null,
        public readonly ?string $temporaryFilesEncrypted = null,
    )
    {}
}

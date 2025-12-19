<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

class PackagerResult
{
    protected string $output;

    protected array $metadata = [];

    public function __construct(string $output, array $metadata = [])
    {
        $this->output = $output;
        $this->metadata = $metadata;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'metadata' => $this->metadata,
        ];
    }
}

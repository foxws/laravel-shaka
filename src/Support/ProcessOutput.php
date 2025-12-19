<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

class ProcessOutput
{
    private array $all;

    private array $errors;

    private array $out;

    public function __construct(array $all, array $errors, array $out)
    {
        $this->all = $all;
        $this->errors = $errors;
        $this->out = $out;
    }

    public function all(): array
    {
        return $this->all;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function out(): array
    {
        return $this->out;
    }
}

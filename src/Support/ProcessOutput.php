<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

class ProcessOutput
{
    /**
     * @param  array<int, string>  $all
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $out
     */
    public function __construct(
        private array $all = [],
        private array $errors = [],
        private array $out = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return $this->all;
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function out(): array
    {
        return $this->out;
    }
}

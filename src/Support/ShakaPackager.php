<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Exceptions\ExecutableNotFoundException;
use Foxws\Shaka\Exceptions\RuntimeException;
use Illuminate\Support\Facades\Process;
use Psr\Log\LoggerInterface;

class ShakaPackager
{
    protected string $binaryPath;

    protected ?LoggerInterface $logger;

    protected int $timeout;

    public function __construct(
        string $binaryPath,
        ?LoggerInterface $logger = null,
        int $timeout = 3600
    ) {
        $this->binaryPath = $binaryPath;
        $this->logger = $logger;
        $this->timeout = $timeout;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $config = $configuration ?? config('laravel-shaka');

        $binaryPath = $config['packager']['binaries'] ?? '/usr/local/bin/packager';

        $timeout = $config['timeout'] ?? 3600;

        // Verify binary exists
        if (! static::isExecutable($binaryPath)) {
            throw new ExecutableNotFoundException(
                "Shaka Packager binary not found at: {$binaryPath}"
            );
        }

        return new self($binaryPath, $logger, $timeout);
    }

    protected static function isExecutable(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }

    public function getName(): string
    {
        return 'packager';
    }

    public function getVersion(): string
    {
        $result = $this->command('--version');

        if (preg_match('/packager version (.+)/i', $result, $matches)) {
            return trim($matches[1]);
        }

        throw new RuntimeException('Cannot parse packager version');
    }

    public function command(string $command, array $options = []): string
    {
        // Parse the command string into an array of arguments to prevent shell injection
        // This avoids using a shell and prevents command injection attacks
        $arguments = $this->parseCommandToArray($command);
        
        // Prepend the binary path as the first argument
        array_unshift($arguments, $this->binaryPath);

        if ($this->logger) {
            $this->logger->debug('Executing packager command', [
                'binary' => $this->binaryPath,
                'arguments' => $arguments,
                'options' => $options,
            ]);
        }

        // Use array format to avoid shell execution and prevent command injection
        $result = Process::timeout($this->timeout)
            ->run($arguments);

        if ($result->failed()) {
            $errorMessage = "Packager command failed: {$result->errorOutput()}";

            if ($this->logger) {
                $this->logger->error($errorMessage, [
                    'binary' => $this->binaryPath,
                    'arguments' => $arguments,
                    'exit_code' => $result->exitCode(),
                    'output' => $result->output(),
                ]);
            }

            throw new RuntimeException($errorMessage);
        }

        if ($this->logger) {
            $this->logger->debug('Packager command completed', [
                'output' => $result->output(),
            ]);
        }

        return $result->output();
    }

    /**
     * Parse a command string into an array of arguments.
     * Handles quoted arguments and escape sequences properly.
     */
    protected function parseCommandToArray(string $command): array
    {
        $arguments = [];
        $length = strlen($command);
        $current = '';
        $inQuote = false;
        $quoteChar = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            // Check for escape sequences
            if ($char === '\\' && $i + 1 < $length) {
                $nextChar = $command[$i + 1];
                
                // Handle escape sequences
                if ($nextChar === '\\' || $nextChar === '"' || $nextChar === "'" || $nextChar === ' ') {
                    // Add the escaped character
                    $current .= $nextChar;
                    $i++; // Skip the next character
                    continue;
                }
                
                // Not a recognized escape sequence, keep the backslash
                $current .= $char;
                continue;
            }

            if (($char === '"' || $char === "'")) {
                if ($inQuote && $char === $quoteChar) {
                    // End of quoted string
                    $inQuote = false;
                    $quoteChar = null;
                } elseif (! $inQuote) {
                    // Start of quoted string
                    $inQuote = true;
                    $quoteChar = $char;
                } else {
                    // Different quote character inside quotes
                    $current .= $char;
                }
            } elseif ($char === ' ' && ! $inQuote) {
                // Space outside quotes - end of argument
                if ($current !== '') {
                    $arguments[] = $current;
                    $current = '';
                }
                // Skip multiple consecutive spaces by not adding empty strings
            } else {
                $current .= $char;
            }
        }

        // Add the last argument if any
        if ($current !== '') {
            $arguments[] = $current;
        }

        return $arguments;
    }

    public function getBinaryPath(): string
    {
        return $this->binaryPath;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}

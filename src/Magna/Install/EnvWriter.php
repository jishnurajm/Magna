<?php

declare(strict_types=1);

namespace Magna\Install;

use RuntimeException;

/**
 * Minimal .env editor: updates keys in place, appends missing ones,
 * quotes values that need it, and never disturbs comments or unrelated
 * lines. Creates the file if it does not exist.
 */
final class EnvWriter
{
    public function __construct(private readonly string $path) {}

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace_callback(
                    $pattern,
                    fn (): string => $line,
                    $contents,
                    1,
                );
            } else {
                $contents = $contents === ''
                    ? $line.PHP_EOL
                    : rtrim($contents, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException("Unable to write environment file at [{$this->path}].");
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('#^[A-Za-z0-9_./:\\\\-]+$#', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}

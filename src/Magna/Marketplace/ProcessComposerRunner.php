<?php

declare(strict_types=1);

namespace Magna\Marketplace;

use Symfony\Component\Process\Process;

/**
 * Default {@see ComposerRunner} — invokes the real Composer binary via a process
 * in the application root. Locates Composer from COMPOSER_BINARY, the PATH, or a
 * project-local composer.phar, and reports unavailable if none respond.
 */
final class ProcessComposerRunner implements ComposerRunner
{
    /** @var list<string>|null Resolved command prefix, cached after first lookup. */
    private ?array $binary = null;

    private bool $resolved = false;

    public function __construct(private readonly string $basePath) {}

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    public function run(array $args, int $timeout = 300): ComposerResult
    {
        $binary = $this->binary();
        if ($binary === null) {
            return new ComposerResult(127, 'Composer was not found on this server.');
        }

        $process = new Process(
            [...$binary, ...$args, '--no-interaction'],
            $this->basePath,
            ['COMPOSER_NO_INTERACTION' => '1'] + getenv(),
            null,
            (float) $timeout,
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            return new ComposerResult(1, $e->getMessage());
        }

        return new ComposerResult(
            $process->getExitCode() ?? 1,
            trim($process->getOutput()."\n".$process->getErrorOutput()),
        );
    }

    /** @return list<string>|null */
    private function binary(): ?array
    {
        if ($this->resolved) {
            return $this->binary;
        }

        $this->resolved = true;

        foreach ($this->candidates() as $candidate) {
            $check = new Process([...$candidate, '--version'], $this->basePath, null, null, 15.0);
            try {
                $check->run();
            } catch (\Throwable) {
                continue;
            }

            if ($check->isSuccessful()) {
                return $this->binary = $candidate;
            }
        }

        return $this->binary = null;
    }

    /** @return list<list<string>> */
    private function candidates(): array
    {
        $candidates = [];

        $env = getenv('COMPOSER_BINARY');
        if (is_string($env) && $env !== '') {
            $candidates[] = str_ends_with($env, '.phar') ? [PHP_BINARY, $env] : [$env];
        }

        $candidates[] = ['composer'];

        $phar = $this->basePath.DIRECTORY_SEPARATOR.'composer.phar';
        if (is_file($phar)) {
            $candidates[] = [PHP_BINARY, $phar];
        }

        return $candidates;
    }
}

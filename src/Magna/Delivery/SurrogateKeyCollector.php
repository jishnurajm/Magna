<?php

declare(strict_types=1);

namespace Magna\Delivery;

/**
 * Accumulates surrogate-key tokens during a delivery response build.
 * Emitted as the Surrogate-Keys header for CDN tag-based invalidation.
 */
final class SurrogateKeyCollector
{
    /** @var list<string> */
    private array $keys = [];

    public function addEntry(string $id): void
    {
        $this->keys[] = 'entry:'.$id;
    }

    public function addType(string $handle): void
    {
        $this->keys[] = 'type:'.$handle;
    }

    public function addMedia(string $id): void
    {
        $this->keys[] = 'media:'.$id;
    }

    public function headerValue(): string
    {
        return implode(' ', array_unique($this->keys));
    }
}

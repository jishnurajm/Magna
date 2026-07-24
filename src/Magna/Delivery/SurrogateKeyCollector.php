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

    public function addLocale(string $locale): void
    {
        if ($locale !== '') {
            $this->keys[] = 'locale:'.$locale;
        }
    }

    public function headerValue(): string
    {
        return implode(' ', array_unique($this->keys));
    }

    /**
     * Convert surrogate keys to internal cache tag names.
     * e.g. 'type:article' → 'magna.delivery.type.article'
     *
     * @return list<string>
     */
    public function cacheTagKeys(): array
    {
        $tags = ['magna.delivery'];
        foreach (array_unique($this->keys) as $key) {
            $tags[] = 'magna.delivery.'.str_replace(':', '.', $key);
        }

        return array_values(array_unique($tags));
    }
}

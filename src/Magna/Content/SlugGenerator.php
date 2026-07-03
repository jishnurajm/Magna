<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * Generate a URL-safe slug from a source string, unique per type+field+locale.
     *
     * Returns an empty string if $source produces no slug-able characters.
     */
    public function generate(ContentType $type, string $fieldHandle, string $source, ?string $locale = null): string
    {
        $base = Str::slug($source);

        if ($base === '') {
            return '';
        }

        $slug = $base;
        $counter = 1;

        while ($this->exists($type, $fieldHandle, $slug, $locale)) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }

    private function exists(ContentType $type, string $fieldHandle, string $slug, ?string $locale): bool
    {
        $query = Entry::type($type->handle)->where($fieldHandle, $slug);

        if ($locale !== null && $locale !== '') {
            $query->where('locale', $locale);
        }

        return $query->exists();
    }
}

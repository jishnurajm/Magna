<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: adds fields or tabs to a content type's admin entry form.
 * Semver-guaranteed from core 1.0. Wired to Filament in Stage 10.
 *
 * @todo Stage 10 — return typed Filament form components instead of array<mixed>.
 */
interface ExtendsEntryForm
{
    /**
     * Return Filament form components to append to the entry form for the given content type.
     *
     * @return array<int, mixed>
     */
    public function entryFormExtensions(string $contentType): array;
}

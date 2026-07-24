<?php

declare(strict_types=1);

namespace Magna\Blocks\Contracts;

/**
 * Implement this interface on a class referenced by a block field's `optionsFrom`
 * key in block.json.  The class must be resolvable from the service container.
 *
 * Example block.json field:
 *
 *   {
 *     "handle": "content_type",
 *     "type": "select",
 *     "label": "Content type",
 *     "optionsFrom": "Magna\\Blocks\\Options\\ContentTypeOptions"
 *   }
 *
 * The options are evaluated lazily — only when the block form renders.
 */
interface ProvidesOptions
{
    /**
     * Return a map of option value → display label.
     *
     * @return array<string, string>
     */
    public function options(): array;
}

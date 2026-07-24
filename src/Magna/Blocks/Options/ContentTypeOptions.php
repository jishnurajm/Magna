<?php

declare(strict_types=1);

namespace Magna\Blocks\Options;

use Magna\Blocks\Contracts\ProvidesOptions;
use Magna\Content\SchemaRegistry;

/**
 * Provides a select list of all registered content type handles,
 * used by the `entries` block's `content_type` field.
 */
final class ContentTypeOptions implements ProvidesOptions
{
    public function __construct(private readonly SchemaRegistry $registry) {}

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->registry->all() as $type) {
            $options[$type->handle] = $type->displayName;
        }
        ksort($options);

        return $options;
    }
}

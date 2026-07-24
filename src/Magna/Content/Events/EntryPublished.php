<?php

declare(strict_types=1);

namespace Magna\Content\Events;

use Magna\Content\Entry;

class EntryPublished
{
    public function __construct(
        public readonly Entry $entry,
        public readonly ?string $actorId,
    ) {}
}

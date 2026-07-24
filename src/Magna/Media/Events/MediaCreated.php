<?php

declare(strict_types=1);

namespace Magna\Media\Events;

use Magna\Media\Media;

class MediaCreated
{
    public function __construct(
        public readonly Media $media,
        public readonly ?string $actorId = null,
    ) {}
}

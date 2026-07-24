<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Magna\Content\ContentType;
use Magna\Delivery\SurrogateKeyCollector;

/** Result of a successful DeliveryController::beginRequest() call. */
final readonly class DeliveryRequestContext
{
    public function __construct(
        public ContentType $contentType,
        public SurrogateKeyCollector $keys,
        public string $cacheKey,
    ) {}
}

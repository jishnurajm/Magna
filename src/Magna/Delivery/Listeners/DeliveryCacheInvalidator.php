<?php

declare(strict_types=1);

namespace Magna\Delivery\Listeners;

use Magna\Content\Events\EntryDeleted;
use Magna\Content\Events\EntryPublished;
use Magna\Content\Events\EntryUnpublished;
use Magna\Content\Events\EntryUpdated;
use Magna\Delivery\EdgeCache\EdgeCacheDispatcher;
use Magna\Delivery\ETagService;
use Magna\Delivery\ResponseCacheService;
use Magna\Media\Events\MediaCreated;
use Magna\Media\Events\MediaDeleted;

/**
 * Flushes ETag and response body caches and dispatches edge-cache purge
 * jobs whenever content or media changes so stale responses are evicted.
 */
final class DeliveryCacheInvalidator
{
    public function __construct(
        private readonly ETagService $etag,
        private readonly ResponseCacheService $responseCache,
        private readonly EdgeCacheDispatcher $edgeCache,
    ) {}

    public function handleEntryPublished(EntryPublished $event): void
    {
        $this->invalidateEntry($event->entry->getHandle());
    }

    public function handleEntryUpdated(EntryUpdated $event): void
    {
        $this->invalidateEntry($event->entry->getHandle());
    }

    public function handleEntryDeleted(EntryDeleted $event): void
    {
        $this->invalidateEntry($event->entry->getHandle());
    }

    public function handleEntryUnpublished(EntryUnpublished $event): void
    {
        $this->invalidateEntry($event->entry->getHandle());
    }

    public function handleMediaCreated(MediaCreated $event): void
    {
        $this->etag->invalidateAllMedia();
        $this->responseCache->invalidateAll();
        $this->edgeCache->dispatch(['media:'.$event->media->id]);
    }

    public function handleMediaDeleted(MediaDeleted $event): void
    {
        $this->etag->invalidateAllMedia();
        $this->responseCache->invalidateAll();
        $this->edgeCache->dispatch(['media:'.$event->media->id]);
    }

    private function invalidateEntry(?string $typeHandle): void
    {
        if ($typeHandle !== null && $typeHandle !== '') {
            $this->etag->invalidateType($typeHandle);
            $this->responseCache->invalidateType($typeHandle);
            $this->edgeCache->dispatch(['type:'.$typeHandle]);
        }
    }
}

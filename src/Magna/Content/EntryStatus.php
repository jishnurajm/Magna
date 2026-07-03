<?php

declare(strict_types=1);

namespace Magna\Content;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}

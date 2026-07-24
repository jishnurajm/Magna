<?php

declare(strict_types=1);

namespace Magna\Settings;

class ContentSettings extends Settings
{
    /** Default status for newly created entries. */
    public string $default_status = 'draft';

    /** Maximum revisions retained per entry (older ones are pruned). */
    public int $revision_limit = 50;

    /** Autosave interval in seconds for the block editor. */
    public int $autosave_interval = 60;
}

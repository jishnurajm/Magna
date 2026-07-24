<?php

declare(strict_types=1);

namespace Magna\Settings;

class ApiSettings extends Settings
{
    /** When false the delivery API returns 503 for all content requests. */
    public bool $api_enabled = true;

    /** Default number of entries returned per page when ?per_page is not specified. */
    public int $default_per_page = 25;

    /** Hard ceiling on ?per_page; requests above this are clamped silently. */
    public int $max_per_page = 100;
}

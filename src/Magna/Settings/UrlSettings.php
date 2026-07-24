<?php

declare(strict_types=1);

namespace Magna\Settings;

class UrlSettings extends Settings
{
    /**
     * Base URL of the frontend application (e.g. https://example.com).
     * Used in admin panel links to the live site, email templates, and
     * the preview URL when no preview_base_url is set.
     */
    public string $frontend_url = '';

    /**
     * CDN base URL for public media files (e.g. https://cdn.example.com).
     * When set, MediaUrlResolver prefixes all public media paths with this
     * URL instead of using the storage disk's URL. Leave blank to serve
     * media directly from the application storage.
     */
    public string $cdn_url = '';

    /**
     * Base URL used to build entry preview links shown in the admin panel.
     * Falls back to frontend_url when blank.
     */
    public string $preview_base_url = '';
}

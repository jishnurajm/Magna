<?php

declare(strict_types=1);

namespace Magna\Settings;

class LocalizationSettings extends Settings
{
    /**
     * All locale codes the site supports, e.g. ['en', 'fr', 'de'].
     * The delivery API accepts ?locale= values from this list.
     *
     * @var list<string>
     */
    public array $available_locales = ['en'];

    /**
     * Locale to fall back to when requested content is missing in the
     * requested locale. Must be present in $available_locales.
     */
    public string $fallback_locale = 'en';

    /**
     * Locale codes that use right-to-left text direction, e.g. ['ar', 'he'].
     * Used by the admin panel and the Pages renderer to set dir="rtl".
     *
     * @var list<string>
     */
    public array $rtl_locales = [];
}

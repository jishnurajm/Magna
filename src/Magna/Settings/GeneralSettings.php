<?php

declare(strict_types=1);

namespace Magna\Settings;

class GeneralSettings extends Settings
{
    public string $site_name = 'Magna CMS';

    public string $site_tagline = '';

    public string $timezone = 'UTC';

    public string $default_locale = 'en';

    public bool $registration_enabled = false;

    // Branding
    public string $favicon_path = '';

    public string $logo_path = '';

    // Regional
    public string $date_format = 'Y-m-d';

    public string $time_format = 'H:i';

    public int $first_day_of_week = 1;

    public string $currency = '';
}

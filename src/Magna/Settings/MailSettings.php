<?php

declare(strict_types=1);

namespace Magna\Settings;

use Magna\Settings\Attributes\Secret;

class MailSettings extends Settings
{
    public string $driver = 'smtp';

    public string $host = 'localhost';

    public int $port = 25;

    public ?string $username = null;

    #[Secret]
    public ?string $password = null;

    public ?string $encryption = null;

    public string $from_address = 'noreply@example.com';

    public string $from_name = 'Magna CMS';
}

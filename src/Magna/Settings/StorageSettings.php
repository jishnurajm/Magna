<?php

declare(strict_types=1);

namespace Magna\Settings;

use Magna\Settings\Attributes\Secret;

class StorageSettings extends Settings
{
    public string $disk = 'local';

    public ?string $s3_key = null;

    #[Secret]
    public ?string $s3_secret = null;

    public ?string $s3_bucket = null;

    public ?string $s3_region = null;

    public ?string $s3_url = null;
}

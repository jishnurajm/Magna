<?php

declare(strict_types=1);

namespace Magna\Users;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}

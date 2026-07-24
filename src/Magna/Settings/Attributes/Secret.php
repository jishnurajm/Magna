<?php

declare(strict_types=1);

namespace Magna\Settings\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Secret {}

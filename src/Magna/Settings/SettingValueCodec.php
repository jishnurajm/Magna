<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Support\Facades\Crypt;
use Magna\Settings\Attributes\Secret;
use ReflectionProperty;

/**
 * Encodes/decodes a single Settings property's value for storage.
 * Properties tagged #[Secret] are transparently encrypted at rest.
 */
class SettingValueCodec
{
    public function isSecret(ReflectionProperty $prop): bool
    {
        return $prop->getAttributes(Secret::class) !== [];
    }

    /** Turn a stored (possibly encrypted) raw value back into a PHP value. */
    public function decode(ReflectionProperty $prop, mixed $rawValue): mixed
    {
        if ($this->isSecret($prop) && is_string($rawValue)) {
            return json_decode(Crypt::decryptString($rawValue), true);
        }

        return $rawValue;
    }

    /** Turn a PHP value into the form that should be written to storage. */
    public function encode(ReflectionProperty $prop, mixed $phpValue): mixed
    {
        if ($this->isSecret($prop)) {
            return Crypt::encryptString(json_encode($phpValue, JSON_THROW_ON_ERROR));
        }

        return $phpValue;
    }
}

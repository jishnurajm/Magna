<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Support\Str;
use Magna\Settings\Attributes\Secret;
use ReflectionClass;
use ReflectionProperty;

/**
 * @phpstan-consistent-constructor
 */
abstract class Settings
{
    public static function group(): string
    {
        return Str::snake(str_replace('Settings', '', class_basename(static::class)));
    }

    public static function get(): static
    {
        $instance = new static;
        /** @var SettingsRepository $repo */
        $repo = app(SettingsRepository::class);
        $repo->hydrate($instance);

        return $instance;
    }

    public function save(): void
    {
        /** @var SettingsRepository $repo */
        $repo = app(SettingsRepository::class);
        $repo->persist($this);
    }

    /**
     * Returns all public properties as an array. Secret properties are
     * masked to '[secret]' when $maskSecrets is true.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $maskSecrets = false): array
    {
        $result = [];
        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $key = $prop->getName();
            $isSecret = $prop->getAttributes(Secret::class) !== [];

            if ($maskSecrets && $isSecret) {
                $result[$key] = '[secret]';
            } else {
                /** @var mixed $value */
                $value = $prop->getValue($this);
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

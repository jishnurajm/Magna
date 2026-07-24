<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionProperty;

class SettingsRepository
{
    public function __construct(
        private readonly SettingValueCodec $codec,
        private readonly SettingsChangeLogger $changeLogger,
    ) {}

    public function hydrate(Settings $instance): void
    {
        $group = $instance::group();
        $stored = $this->storedValues($group);
        $ref = new ReflectionClass($instance);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $key = $prop->getName();

            if (! array_key_exists($key, $stored)) {
                continue;
            }

            $prop->setValue($instance, $this->codec->decode($prop, $stored[$key]));
        }
    }

    public function persist(Settings $instance): void
    {
        $group = $instance::group();
        $ref = new ReflectionClass($instance);

        $before = $this->auditSnapshot($group, $ref);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            /** @var mixed $phpValue */
            $phpValue = $prop->getValue($instance);

            Setting::updateOrCreate(
                ['group' => $group, 'key' => $prop->getName()],
                ['value' => $this->codec->encode($prop, $phpValue)],
            );
        }

        Cache::forget("magna-settings:{$group}");

        $after = $this->auditSnapshot($group, $ref);
        $this->changeLogger->log($group, $before, $after);
    }

    /** @return array<string, mixed> */
    private function storedValues(string $group): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(
            "magna-settings:{$group}",
            now()->addHour(),
            fn (): array => Setting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $s): array => [$s->key => $s->value])
                ->all(),
        );
    }

    /**
     * Masked snapshot of a settings group's current values, for audit logging.
     *
     * @param  ReflectionClass<Settings>  $ref
     * @return array<string, mixed>
     */
    private function auditSnapshot(string $group, ReflectionClass $ref): array
    {
        $stored = Setting::query()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(fn (Setting $s): array => [$s->key => $s->value])
            ->all();

        $result = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $key = $prop->getName();

            if ($this->codec->isSecret($prop)) {
                $result[$key] = '[secret]';
            } elseif (array_key_exists($key, $stored)) {
                $result[$key] = $stored[$key];
            }
        }

        return $result;
    }
}

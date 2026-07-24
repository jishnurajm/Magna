<?php

declare(strict_types=1);

namespace Magna\Media;

class ConversionPresetRegistry
{
    /** @var array<string, ConversionPreset> */
    private array $presets = [];

    public function register(ConversionPreset $preset): void
    {
        $this->presets[$preset->name] = $preset;
    }

    public function get(string $name): ?ConversionPreset
    {
        return $this->presets[$name] ?? null;
    }

    /** @return list<ConversionPreset> */
    public function all(): array
    {
        return array_values($this->presets);
    }
}

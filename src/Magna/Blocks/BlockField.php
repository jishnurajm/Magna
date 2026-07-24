<?php

declare(strict_types=1);

namespace Magna\Blocks;

use Magna\Blocks\Contracts\ProvidesOptions;

/**
 * Represents one field definition inside a block.json schema.
 *
 * @phpstan-type BlockFieldArray array{
 *   handle: string,
 *   type: string,
 *   label?: string,
 *   required?: bool,
 *   default?: mixed,
 *   options?: array<string, string>,
 *   optionsFrom?: string,
 *   multiple?: bool,
 * }
 */
final class BlockField
{
    public function __construct(
        public readonly string $handle,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly mixed $default,
        /** @var array<string, string> */
        public readonly array $options,
        public readonly ?string $optionsFrom,
        public readonly bool $multiple,
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $handle = isset($data['handle']) && is_string($data['handle']) ? $data['handle'] : '';
        if ($handle === '') {
            throw new \InvalidArgumentException('Block field must have a non-empty "handle".');
        }

        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'text';

        /** @var array<string, string> $options */
        $options = [];
        if (isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $options[$k] = $v;
                }
            }
        }

        $optionsFrom = isset($data['optionsFrom']) && is_string($data['optionsFrom'])
            ? $data['optionsFrom']
            : null;

        return new self(
            handle: $handle,
            type: $type,
            label: isset($data['label']) && is_string($data['label']) ? $data['label'] : ucwords(str_replace('_', ' ', $handle)),
            required: isset($data['required']) && (bool) $data['required'],
            default: $data['default'] ?? null,
            options: $options,
            optionsFrom: $optionsFrom,
            multiple: isset($data['multiple']) && (bool) $data['multiple'],
        );
    }

    /**
     * Resolve dynamic options from the `optionsFrom` class at form render time.
     * Falls back to the static `options` array when `optionsFrom` is not set.
     *
     * @return array<string, string>
     */
    public function resolveOptions(): array
    {
        if ($this->optionsFrom !== null
            && class_exists($this->optionsFrom)
            && is_a($this->optionsFrom, ProvidesOptions::class, true)
        ) {
            return app($this->optionsFrom)->options();
        }

        return $this->options;
    }
}

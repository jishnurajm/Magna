<?php

declare(strict_types=1);

namespace Magna\Content;

use Magna\Content\Exceptions\SchemaException;
use Magna\Content\FieldTypes\FieldType;

final class Field
{
    /**
     * @param  array<string, mixed>  $rawData  Original JSON data — used for round-trip serialisation.
     */
    public function __construct(
        public readonly string $handle,
        public readonly FieldType $type,
        public readonly bool $required,
        public readonly array $rawData,
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     *
     * @throws SchemaException
     */
    public static function fromArray(array $data, FieldTypeRegistry $registry): self
    {
        $handle = $data['handle'] ?? null;
        if (! is_string($handle) || $handle === '') {
            throw new SchemaException('Field "handle" must be a non-empty string.');
        }

        $typeName = $data['type'] ?? null;
        if (! is_string($typeName) || $typeName === '') {
            throw new SchemaException("Field \"{$handle}\" must specify a \"type\".");
        }

        $required = isset($data['required']) && (bool) $data['required'];

        // Extract options: all keys except the reserved ones, narrowed to string keys.
        $reserved = ['handle', 'type', 'required'];
        $options = [];
        $rawData = [];
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $rawData[$key] = $value;
            if (! in_array($key, $reserved, true)) {
                $options[$key] = $value;
            }
        }

        return new self(
            handle: $handle,
            type: $registry->make($typeName, $options),
            required: $required,
            rawData: $rawData,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->rawData;
    }
}

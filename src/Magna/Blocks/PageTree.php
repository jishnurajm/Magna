<?php

declare(strict_types=1);

namespace Magna\Blocks;

use Illuminate\Support\Str;

/**
 * Value objects for the Section → Column → Block page tree.
 *
 * These are thin, immutable read-models for storing and rendering the tree.
 * Mutation happens in the Livewire block editor component; the result is
 * serialised to the entry's blocks_data column as plain JSON.
 */
final class BlockNode
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $block,
        public readonly array $settings,
        public readonly array $data,
    ) {}

    /**
     * @param  array<mixed, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : (string) Str::ulid(),
            block: isset($raw['block']) && is_string($raw['block']) ? $raw['block'] : '',
            settings: is_array($raw['settings'] ?? null) ? $raw['settings'] : [],
            data: is_array($raw['data'] ?? null) ? $raw['data'] : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'block' => $this->block,
            'settings' => $this->settings,
            'data' => $this->data,
        ];
    }
}

final class ColumnNode
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<BlockNode>  $blocks
     */
    public function __construct(
        public readonly string $id,
        public readonly int $span,
        public readonly array $settings,
        public readonly array $blocks,
    ) {}

    /**
     * @param  array<mixed, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $blocks = [];
        if (isset($raw['blocks']) && is_array($raw['blocks'])) {
            foreach ($raw['blocks'] as $blockRaw) {
                if (is_array($blockRaw)) {
                    $blocks[] = BlockNode::fromArray($blockRaw);
                }
            }
        }

        $spanRaw = $raw['span'] ?? 12;
        $span = is_int($spanRaw) ? $spanRaw : 12;

        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : (string) Str::ulid(),
            span: max(1, min(12, $span)),
            settings: is_array($raw['settings'] ?? null) ? $raw['settings'] : [],
            blocks: $blocks,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'span' => $this->span,
            'settings' => $this->settings,
            'blocks' => array_map(fn (BlockNode $b) => $b->toArray(), $this->blocks),
        ];
    }
}

final class SectionNode
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<ColumnNode>  $columns
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $settings,
        public readonly array $columns,
    ) {}

    /**
     * @param  array<mixed, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $columns = [];
        if (isset($raw['columns']) && is_array($raw['columns'])) {
            foreach ($raw['columns'] as $colRaw) {
                if (is_array($colRaw)) {
                    $columns[] = ColumnNode::fromArray($colRaw);
                }
            }
        }

        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : (string) Str::ulid(),
            type: 'section',
            settings: is_array($raw['settings'] ?? null) ? $raw['settings'] : [],
            columns: $columns,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'settings' => $this->settings,
            'columns' => array_map(fn (ColumnNode $c) => $c->toArray(), $this->columns),
        ];
    }

    /**
     * Return the tokenOverrides from section settings (empty array if none).
     *
     * @return array<string, string>
     */
    public function tokenOverrides(): array
    {
        $overrides = $this->settings['tokenOverrides'] ?? [];

        if (! is_array($overrides)) {
            return [];
        }

        $result = [];
        foreach ($overrides as $key => $value) {
            if (is_string($key) && $key !== ''
                && ! str_contains($key, ';') && ! str_contains($key, ':')
                && is_string($value) && $value !== ''
                && ! str_contains($value, ';')
                && ! str_contains($value, '(')  // blocks url(), calc(), and similar expressions
            ) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

/**
 * The full page tree — a list of SectionNodes.
 */
final class PageTree
{
    /** @param list<SectionNode> $sections */
    public function __construct(public readonly array $sections) {}

    /**
     * Parse raw blocks_data JSON (already decoded as an array) into a PageTree.
     *
     * @param  array<int, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $sections = [];
        foreach ($raw as $sectionRaw) {
            if (is_array($sectionRaw)) {
                $sections[] = SectionNode::fromArray($sectionRaw);
            }
        }

        return new self($sections);
    }

    /**
     * Decode a JSON string into a PageTree.
     */
    public static function fromJson(string $json): self
    {
        $raw = json_decode($json, true);

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (SectionNode $s) => $s->toArray(), $this->sections);
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

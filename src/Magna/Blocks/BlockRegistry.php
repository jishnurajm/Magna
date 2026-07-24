<?php

declare(strict_types=1);

namespace Magna\Blocks;

/**
 * Central registry of all available block types.
 *
 * Core blocks are registered at boot by BlocksServiceProvider.
 * Plugin-registered blocks are added via register() when a plugin is enabled
 * (fills the Stage 4 RegistersBlocks contract stub).
 *
 * NOTE: The `form` block is NOT in the core library — it is registered by
 * the magna/forms plugin (Stage 14). Plugin-defined blocks extend this
 * standard library.
 */
final class BlockRegistry
{
    /** @var array<string, BlockDefinition> keyed by handle */
    private array $blocks = [];

    public function register(BlockDefinition $definition): void
    {
        $this->blocks[$definition->handle] = $definition;
    }

    /**
     * Load and register all block.json files from a directory.
     */
    public function loadFromDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*.json') ?: [] as $file) {
            $this->register(BlockDefinition::fromFile($file));
        }
    }

    public function has(string $handle): bool
    {
        return isset($this->blocks[$handle]);
    }

    public function get(string $handle): ?BlockDefinition
    {
        return $this->blocks[$handle] ?? null;
    }

    /**
     * @return array<string, BlockDefinition>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Return blocks grouped by category, sorted alphabetically within each group.
     *
     * @return array<string, list<BlockDefinition>>
     */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->blocks as $block) {
            $groups[$block->category][] = $block;
        }
        ksort($groups);

        return $groups;
    }

    /**
     * Return the total number of registered blocks.
     */
    public function count(): int
    {
        return count($this->blocks);
    }
}

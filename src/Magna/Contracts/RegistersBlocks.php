<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: contributes block definitions.
 * Semver-guaranteed from core 1.0. Fully wired in Stage 11.
 *
 * @todo Stage 11 — return typed BlockDefinition objects instead of paths.
 */
interface RegistersBlocks
{
    /**
     * Return absolute paths to directories that contain block.json definitions.
     *
     * @return list<string>
     */
    public function blockPaths(): array;
}

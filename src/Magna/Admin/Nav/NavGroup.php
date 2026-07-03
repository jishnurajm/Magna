<?php

declare(strict_types=1);

namespace Magna\Admin\Nav;

/**
 * Represents a navigation group in the Magna admin sidebar.
 * Used by plugins that implement RegistersAdminNavigation.
 */
final class NavGroup
{
    /** @var list<NavItem> */
    private array $items = [];

    public function __construct(
        public readonly string $label,
        public readonly string $icon = 'puzzle-piece',
    ) {}

    public static function make(string $label, string $icon = 'puzzle-piece'): self
    {
        return new self($label, $icon);
    }

    /**
     * @param  list<NavItem>  $items
     */
    public function items(array $items): self
    {
        $clone = new self($this->label, $this->icon);
        $clone->items = $items;

        return $clone;
    }

    /**
     * @return list<NavItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}

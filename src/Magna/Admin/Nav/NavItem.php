<?php

declare(strict_types=1);

namespace Magna\Admin\Nav;

/**
 * Represents a single item within a NavGroup.
 */
final class NavItem
{
    private ?string $requiredPermission = null;

    private function __construct(
        public readonly string $label,
        public readonly ?string $route = null,
        public readonly ?string $resourceClass = null,
    ) {}

    /**
     * A link to a Filament resource (Stage 10 wires this up).
     */
    public static function resource(string $resourceClass): self
    {
        return new self(label: class_basename($resourceClass), resourceClass: $resourceClass);
    }

    /**
     * A link to a named route or page.
     */
    public static function page(string $label, string $route): self
    {
        return new self(label: $label, route: $route);
    }

    /**
     * Restrict this item to users holding the given permission.
     */
    public function can(string $permission): self
    {
        $clone = new self($this->label, $this->route, $this->resourceClass);
        $clone->requiredPermission = $permission;

        return $clone;
    }

    public function getRequiredPermission(): ?string
    {
        return $this->requiredPermission;
    }
}

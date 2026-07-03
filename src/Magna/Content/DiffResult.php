<?php

declare(strict_types=1);

namespace Magna\Content;

final class DiffResult
{
    /** @param list<DiffChange> $changes */
    public function __construct(public readonly array $changes = []) {}

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function hasDestructive(): bool
    {
        foreach ($this->changes as $change) {
            if ($change->destructive) {
                return true;
            }
        }

        return false;
    }

    /** @return list<DiffChange> */
    public function destructive(): array
    {
        return array_values(array_filter($this->changes, fn (DiffChange $c): bool => $c->destructive));
    }

    /** @return list<DiffChange> */
    public function nonDestructive(): array
    {
        return array_values(array_filter($this->changes, fn (DiffChange $c): bool => ! $c->destructive));
    }
}

<?php

declare(strict_types=1);

namespace Magna\Auth\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Magna\Audit\AuditLog;
use Magna\Auth\PermissionMatcher;
use Magna\Auth\Role;

/**
 * @phpstan-require-extends Model
 */
trait HasRoles
{
    /**
     * Grants resolved from this user's roles, memoized per instance.
     *
     * @var list<string>|null
     */
    private ?array $resolvedGrants = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function assignRole(Role|string $role): void
    {
        $role = $this->resolveRole($role);

        $this->roles()->syncWithoutDetaching([$role->getKey()]);
        $this->forgetResolvedGrants();

        $actorId = Auth::id();
        AuditLog::record(
            action: 'roles.assigned',
            actorId: $actorId !== null ? (string) $actorId : null,
            actorType: $actorId !== null ? 'user' : 'system',
            ip: request()->ip(),
            subject: $this,
            after: ['role' => $role->handle],
        );
    }

    public function removeRole(Role|string $role): void
    {
        $role = $this->resolveRole($role);

        $this->roles()->detach($role->getKey());
        $this->forgetResolvedGrants();

        $actorId = Auth::id();
        AuditLog::record(
            action: 'roles.removed',
            actorId: $actorId !== null ? (string) $actorId : null,
            actorType: $actorId !== null ? 'user' : 'system',
            ip: request()->ip(),
            subject: $this,
            before: ['role' => $role->handle],
        );
    }

    public function hasRole(string $handle): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->handle === $handle);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->is_super_admin);
    }

    /**
     * Every grant this user holds through their roles.
     *
     * @return list<string>
     */
    public function permissionGrants(): array
    {
        return $this->resolvedGrants ??= array_values(
            $this->roles
                ->flatMap(fn (Role $role): array => $role->grants())
                ->unique()
                ->all(),
        );
    }

    /**
     * Whether any of this user's grants (including wildcards) covers the
     * given concrete permission key. Does not consult the registry — the
     * Gate integration is responsible for rejecting unregistered keys.
     */
    public function hasPermissionGrant(string $key): bool
    {
        return PermissionMatcher::anyMatches($this->permissionGrants(), $key);
    }

    public function forgetResolvedGrants(): void
    {
        $this->resolvedGrants = null;
        $this->unsetRelation('roles');
    }

    private function resolveRole(Role|string $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $resolved = Role::query()->where('handle', $role)->first();

        if ($resolved === null) {
            throw new InvalidArgumentException("Role with handle [{$role}] does not exist.");
        }

        return $resolved;
    }
}

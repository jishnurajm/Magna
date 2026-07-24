<?php

declare(strict_types=1);

namespace Magna\Auth;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Magna\Users\User;

#[Fillable(['handle', 'name', 'description', 'is_super_admin'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Grant one or more permission keys (wildcards allowed) to this role.
     */
    public function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->permissions()->firstOrCreate(['permission' => $permission]);
        }
    }

    public function revoke(string $permission): void
    {
        $this->permissions()->where('permission', $permission)->delete();
    }

    /**
     * All grants held by this role.
     *
     * @return list<string>
     */
    public function grants(): array
    {
        return array_values(
            $this->permissions()
                ->get()
                ->map(fn (RolePermission $permission): string => $permission->permission)
                ->all(),
        );
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}

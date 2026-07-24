<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single permission grant held by a role. The grant may be a concrete
 * registered key ("users.manage") or a wildcard ("blog.*").
 */
#[Fillable(['permission'])]
class RolePermission extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}

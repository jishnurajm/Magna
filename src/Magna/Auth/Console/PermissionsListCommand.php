<?php

declare(strict_types=1);

namespace Magna\Auth\Console;

use Illuminate\Console\Command;
use Magna\Auth\PermissionMatcher;
use Magna\Auth\PermissionRegistry;
use Magna\Auth\Role;

class PermissionsListCommand extends Command
{
    protected $signature = 'magna:permissions:list';

    protected $description = 'List all registered permission keys and the roles that hold them';

    public function handle(PermissionRegistry $registry): int
    {
        $permissions = $registry->all();

        if ($permissions === []) {
            $this->warn('No permissions are registered.');

            return self::SUCCESS;
        }

        /** @var list<Role> $roles */
        $roles = Role::query()->with('permissions')->get()->all();

        $rows = [];

        foreach ($permissions as $key => $description) {
            $holders = [];

            foreach ($roles as $role) {
                if ($role->is_super_admin || PermissionMatcher::anyMatches($role->grants(), $key)) {
                    $holders[] = $role->handle;
                }
            }

            $rows[] = [$key, $description ?? '', implode(', ', $holders)];
        }

        $this->table(['Permission', 'Description', 'Held by roles'], $rows);
        $this->line(sprintf('%d permission(s) registered.', count($permissions)));

        return self::SUCCESS;
    }
}

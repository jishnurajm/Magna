<?php

declare(strict_types=1);

namespace Magna\HelloWorld;

use Magna\Admin\Nav\NavGroup;
use Magna\Admin\Nav\NavItem;
use Magna\Contracts\RegistersAdminNavigation;
use Magna\Plugins\Plugin;

/**
 * Entry class for the magna/hello-world example plugin.
 * Demonstrates: nav registration, API routes, and a permission.
 */
class HelloWorldPlugin extends Plugin implements RegistersAdminNavigation
{
    public function adminNavigation(): NavGroup
    {
        return NavGroup::make('Hello World', icon: 'star')->items([
            NavItem::page('Greet', route: 'hello-world.greet')
                ->can('hello-world.greet'),
        ]);
    }
}

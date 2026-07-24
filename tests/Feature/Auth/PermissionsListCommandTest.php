<?php

declare(strict_types=1);

use Database\Seeders\RoleSeeder;

it('lists registered permissions with the roles that hold them', function (): void {
    $this->seed(RoleSeeder::class);

    $this->artisan('magna:permissions:list')
        ->expectsOutputToContain('users.manage')
        ->expectsOutputToContain('audit.view')
        ->assertSuccessful();
});

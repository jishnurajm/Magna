<?php

declare(strict_types=1);

namespace Magna\Privacy\Commands;

use Magna\Users\User;

trait ResolvesPrivacyUser
{
    private function resolveUser(): ?User
    {
        $id = $this->option('id') ?? $this->argument('identifier');

        if (! is_string($id) || $id === '') {
            $this->error('Provide a user email or ULID as argument, or use --id=.');

            return null;
        }

        $user = str_contains($id, '@')
            ? User::query()->where('email', $id)->first()
            : User::query()->find($id);

        if (! $user instanceof User) {
            $this->error("User not found: {$id}");

            return null;
        }

        return $user;
    }
}
